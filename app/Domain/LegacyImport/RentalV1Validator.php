<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;
use App\Models\LegacyImportRow;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RentalV1Validator
{
    public function __construct(private readonly LegacyImportRecorder $recorder) {}

    public function validate(LegacyImportBatch $batch, ?int $userId = null): LegacyImportBatch
    {
        $this->guardStatus($batch);
        $this->recorder->transition($batch, 'validating', 'validation_started', $userId);

        try {
            $targetLabel = trim((string) $batch->source_city) !== ''
                ? (string) $batch->source_city
                : ((string) $batch->import_prefix ?: 'tujuan import');

            DB::transaction(function () use ($batch, $targetLabel): void {
                DB::table('legacy_import_issues')->where('batch_id', $batch->id)->delete();

                $referenceKeys = $this->referenceKeys($batch);
                $duplicateValues = $this->duplicateValues($batch);
                $now = now();

                foreach (RentalV1Definition::tables() as $sourceTable) {
                    $definition = RentalV1Definition::table($sourceTable);

                    if (! RentalV1Definition::isExecutable($sourceTable)) {
                        $skipped = DB::table('legacy_import_rows')
                            ->where('batch_id', $batch->id)
                            ->where('source_table', $sourceTable)
                            ->update([
                                'status' => 'skipped',
                                'issue_count' => 0,
                                'updated_at' => $now,
                            ]);

                        if ($skipped > 0) {
                            DB::table('legacy_import_issues')->insert([
                                'batch_id' => $batch->id,
                                'legacy_import_row_id' => null,
                                'severity' => 'warning',
                                'code' => 'TABLE_EXCLUDED',
                                'field' => null,
                                'message' => "{$definition['label']} disimpan untuk audit, tetapi tidak dieksekusi otomatis.",
                                'original_value' => $sourceTable,
                                'suggested_resolution' => json_encode([
                                    'action' => 'review_after_core_import',
                                ], JSON_THROW_ON_ERROR),
                                'status' => 'open',
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }

                        continue;
                    }

                    LegacyImportRow::query()
                        ->where('batch_id', $batch->id)
                        ->where('source_table', $sourceTable)
                        ->orderBy('id')
                        ->chunkById(250, function ($rows) use (
                            $batch,
                            $definition,
                            $referenceKeys,
                            $duplicateValues,
                            $now,
                            $targetLabel,
                        ): void {
                            foreach ($rows as $row) {
                                $issues = $this->issuesForRow(
                                    $row,
                                    $definition,
                                    $referenceKeys,
                                    $duplicateValues,
                                    $targetLabel,
                                );
                                $status = $this->statusFromIssues($issues);

                                $row->update([
                                    'status' => $status,
                                    'issue_count' => count($issues),
                                ]);

                                if ($issues === []) {
                                    continue;
                                }

                                DB::table('legacy_import_issues')->insert(array_map(
                                    static fn (array $issue): array => [
                                        'batch_id' => $batch->id,
                                        'legacy_import_row_id' => $row->id,
                                        'severity' => $issue['severity'],
                                        'code' => $issue['code'],
                                        'field' => $issue['field'] ?? null,
                                        'message' => $issue['message'],
                                        'original_value' => isset($issue['original_value'])
                                            ? mb_substr((string) $issue['original_value'], 0, 65000)
                                            : null,
                                        'suggested_resolution' => isset($issue['suggested_resolution'])
                                            ? json_encode($issue['suggested_resolution'], JSON_THROW_ON_ERROR)
                                            : null,
                                        'status' => 'open',
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ],
                                    $issues,
                                ));
                            }
                        });
                }

                $this->updateTableStats($batch);
            });

            $counts = DB::table('legacy_import_rows')
                ->where('batch_id', $batch->id)
                ->select('status', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $options = $batch->options ?? [];
            unset($options['failed_step']);

            $batch->update([
                'status' => 'validated',
                'valid_rows' => (int) ($counts['valid'] ?? 0),
                'warning_rows' => (int) ($counts['warning'] ?? 0),
                'error_rows' => (int) ($counts['error'] ?? 0),
                'skipped_rows' => (int) ($counts['skipped'] ?? 0),
                'options' => $options,
                'failure_message' => null,
                'validated_at' => now(),
                'failed_at' => null,
            ]);

            $this->recorder->event(
                $batch->fresh(),
                'validation_completed',
                $userId,
                'validating',
                'validated',
                [
                    'valid_rows' => $batch->valid_rows,
                    'warning_rows' => $batch->warning_rows,
                    'error_rows' => $batch->error_rows,
                    'skipped_rows' => $batch->skipped_rows,
                ],
            );

            return $batch->fresh();
        } catch (Throwable $exception) {
            $this->recorder->fail($batch->fresh(), 'validation', $exception->getMessage(), $userId);

            throw $exception;
        }
    }

    private function guardStatus(LegacyImportBatch $batch): void
    {
        $retry = $batch->status === 'failed'
            && ($batch->options['failed_step'] ?? null) === 'validation';

        if (
            ! in_array($batch->status, ['previewed', 'validated', 'queued_validation'], true)
            && ! $retry
        ) {
            throw new RuntimeException("Batch berstatus [{$batch->status}] belum siap divalidasi.");
        }

        $options = $batch->options ?? [];

        if (
            ($options['import_prefix'] ?? null) !== $batch->import_prefix
            || ($options['source_city'] ?? null) !== $batch->source_city
        ) {
            throw new RuntimeException(
                'Preview belum memakai identitas kota/PREFIX terbaru. Simpan tujuan import lalu jalankan Preview ulang.',
            );
        }
    }

    /**
     * @return array<string, array<string, true>>
     */
    private function referenceKeys(LegacyImportBatch $batch): array
    {
        $tables = [];

        foreach (RentalV1Definition::TABLES as $definition) {
            foreach (($definition['references'] ?? []) as [$sourceTable]) {
                $tables[$sourceTable] = true;
            }
        }

        $keys = [];

        foreach (array_keys($tables) as $table) {
            $keys[$table] = array_fill_keys(
                DB::table('legacy_import_rows')
                    ->where('batch_id', $batch->id)
                    ->where('source_table', $table)
                    ->pluck('legacy_key')
                    ->map(static fn ($value): string => (string) $value)
                    ->all(),
                true,
            );
        }

        return $keys;
    }

    /**
     * @return array{customer_identity: array<string, int>, customer_phone: array<string, int>, product_serial: array<string, int>}
     */
    private function duplicateValues(LegacyImportBatch $batch): array
    {
        $identity = [];
        $phone = [];
        $serial = [];

        LegacyImportRow::query()
            ->where('batch_id', $batch->id)
            ->where('source_table', 'customer')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$identity, &$phone): void {
                foreach ($rows as $row) {
                    $payload = $row->payload;
                    $identityNumber = $this->duplicateKey($payload['customer_noid'] ?? null);
                    $phoneNumber = $this->duplicateKey($payload['customer_nohp'] ?? null);

                    if ($identityNumber !== null) {
                        $identity[$identityNumber] = ($identity[$identityNumber] ?? 0) + 1;
                    }

                    if ($phoneNumber !== null) {
                        $phone[$phoneNumber] = ($phone[$phoneNumber] ?? 0) + 1;
                    }
                }
            });

        LegacyImportRow::query()
            ->where('batch_id', $batch->id)
            ->where('source_table', 'rent_product')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$serial): void {
                foreach ($rows as $row) {
                    $key = $this->duplicateKey($row->payload['rentproduct_serial_number'] ?? null);

                    if ($key !== null) {
                        $serial[$key] = ($serial[$key] ?? 0) + 1;
                    }
                }
            });

        return [
            'customer_identity' => $identity,
            'customer_phone' => $phone,
            'product_serial' => $serial,
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, array<string, true>>  $referenceKeys
     * @param  array<string, array<string, int>>  $duplicateValues
     * @return list<array<string, mixed>>
     */
    private function issuesForRow(
        LegacyImportRow $row,
        array $definition,
        array $referenceKeys,
        array $duplicateValues,
        string $targetLabel,
    ): array {
        $payload = $row->payload;
        $issues = [];

        foreach (($definition['required'] ?? []) as $field) {
            if ($this->blank($payload[$field] ?? null)) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'REQUIRED_VALUE_MISSING',
                    'field' => $field,
                    'message' => "Kolom wajib [{$field}] kosong.",
                    'original_value' => $payload[$field] ?? null,
                ];
            }
        }

        foreach (($definition['references'] ?? []) as $field => [$targetTable]) {
            $value = $payload[$field] ?? null;

            if ($this->blank($value) || (string) $value === '0') {
                continue;
            }

            if (! isset($referenceKeys[$targetTable][(string) $value])) {
                $issues[] = [
                    'severity' => 'error',
                    'code' => 'REFERENCE_NOT_FOUND',
                    'field' => $field,
                    'message' => "Referensi [{$field}] tidak ditemukan pada tabel [{$targetTable}].",
                    'original_value' => $value,
                ];
            }
        }

        return [
            ...$issues,
            ...$this->businessIssues($row->source_table, $payload, $duplicateValues),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, int>>  $duplicates
     * @return list<array<string, mixed>>
     */
    private function businessIssues(string $table, array $payload, array $duplicates): array
    {
        $issues = [];

        if ($table === 'customer') {
            if (RentalV1Value::gender($payload['customer_jeniskelamin'] ?? null) === null) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'CUSTOMER_GENDER_MISSING',
                    'field' => 'customer_jeniskelamin',
                    'message' => 'Jenis kelamin pelanggan kosong atau tidak dikenali.',
                    'original_value' => $payload['customer_jeniskelamin'] ?? null,
                    'suggested_resolution' => ['target' => null, 'label' => 'Belum ditentukan'],
                ];
            }

            foreach ([
                'customer_noid' => 'customer_identity',
                'customer_nohp' => 'customer_phone',
            ] as $field => $bucket) {
                $key = $this->duplicateKey($payload[$field] ?? null);

                if ($key !== null && ($duplicates[$bucket][$key] ?? 0) > 1) {
                    $issues[] = [
                        'severity' => 'warning',
                        'code' => $field === 'customer_noid'
                            ? 'DUPLICATE_CUSTOMER_IDENTITY'
                            : 'DUPLICATE_CUSTOMER_PHONE',
                        'field' => $field,
                        'message' => "Nilai [{$field}] dipakai oleh lebih dari satu pelanggan.",
                        'original_value' => $payload[$field],
                        'suggested_resolution' => ['action' => 'review_duplicate_after_import'],
                    ];
                }
            }
        }

        if ($table === 'rent_product') {
            if ($this->blank($payload['rentproduct_name'] ?? null)) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'PRODUCT_NAME_MISSING',
                    'field' => 'rentproduct_name',
                    'message' => 'Nama produk kosong dan akan menggunakan nama fallback berbasis legacy ID.',
                    'original_value' => $payload['rentproduct_name'] ?? null,
                    'suggested_resolution' => [
                        'value' => 'Legacy Product '.RentalV1Value::integer(
                            $payload['rentproduct_id'] ?? null,
                        ),
                    ],
                ];
            }

            $serial = $this->duplicateKey($payload['rentproduct_serial_number'] ?? null);

            if ($serial !== null && ($duplicates['product_serial'][$serial] ?? 0) > 1) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'DUPLICATE_ASSET_SERIAL',
                    'field' => 'rentproduct_serial_number',
                    'message' => 'Nomor seri aset digunakan lebih dari sekali.',
                    'original_value' => $payload['rentproduct_serial_number'],
                ];
            }
        }

        if ($table === 'trx_booking' && ($payload['booking_status'] ?? null) === 'pesan') {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'OPEN_LEGACY_BOOKING',
                'field' => 'booking_status',
                'message' => 'Booking lama masih berstatus pesan dan akan menjadi confirmed.',
                'original_value' => 'pesan',
            ];
        }

        if ($table === 'trx_rental') {
            foreach ([
                'rental_date_start',
                'rental_date_end',
                'rental_date_kembali',
            ] as $field) {
                $rawDateTime = $payload[$field] ?? null;

                if (
                    RentalV1Value::dateTime($rawDateTime) !== null
                    && RentalV1Value::operationalDateTime($rawDateTime) === null
                ) {
                    $issues[] = [
                        'severity' => 'warning',
                        'code' => 'LEGACY_DATETIME_SENTINEL',
                        'field' => $field,
                        'message' => "Datetime [{$field}] berada pada tahun sentinel 1899/1900 dan akan direkonstruksi saat execute.",
                        'original_value' => $rawDateTime,
                        'suggested_resolution' => [
                            'action' => 'reconstruct_from_neighboring_rental',
                        ],
                    ];
                }
            }

            if (in_array($payload['rental_status'] ?? null, ['pinjam', 'perpanjangan'], true)) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'OPEN_LEGACY_RENTAL',
                    'field' => 'rental_status',
                    'message' => sprintf(
                        'Rental lama masih aktif dan akan memengaruhi status aset di cabang %s.',
                        $targetLabel,
                    ),
                    'original_value' => $payload['rental_status'],
                ];
            }

            $total = (float) ($payload['rental_total_akhir'] ?? 0);
            $paid = (float) ($payload['rental_bayar'] ?? 0);

            if ($paid + 0.01 < $total) {
                $issues[] = [
                    'severity' => 'warning',
                    'code' => 'RENTAL_BALANCE_REMAINS',
                    'field' => 'rental_bayar',
                    'message' => 'Nilai pembayaran tercatat lebih kecil daripada total akhir.',
                    'original_value' => $payload['rental_bayar'] ?? null,
                    'suggested_resolution' => ['balance_due' => max($total - $paid, 0)],
                ];
            }
        }

        if ($table === 'trx_extrarental' && ($payload['extrarental_status'] ?? null) === 'perpanjangan') {
            $issues[] = [
                'severity' => 'warning',
                'code' => 'OPEN_LEGACY_EXTENSION',
                'field' => 'extrarental_status',
                'message' => 'Perpanjangan lama masih aktif dan akan diimpor sebagai approved.',
                'original_value' => 'perpanjangan',
            ];
        }

        return $issues;
    }

    /** @param list<array<string, mixed>> $issues */
    private function statusFromIssues(array $issues): string
    {
        if (collect($issues)->contains(fn (array $issue): bool => $issue['severity'] === 'error')) {
            return 'error';
        }

        return $issues === [] ? 'valid' : 'warning';
    }

    private function updateTableStats(LegacyImportBatch $batch): void
    {
        foreach (RentalV1Definition::tables() as $sourceTable) {
            $counts = DB::table('legacy_import_rows')
                ->where('batch_id', $batch->id)
                ->where('source_table', $sourceTable)
                ->select('status', DB::raw('COUNT(*) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            DB::table('legacy_import_tables')
                ->where('batch_id', $batch->id)
                ->where('source_table', $sourceTable)
                ->update([
                    'status' => isset($counts['skipped']) ? 'excluded' : 'validated',
                    'valid_rows' => (int) ($counts['valid'] ?? 0),
                    'warning_rows' => (int) ($counts['warning'] ?? 0),
                    'error_rows' => (int) ($counts['error'] ?? 0),
                    'updated_at' => now(),
                ]);
        }
    }

    private function duplicateKey(mixed $value): ?string
    {
        $value = RentalV1Value::string($value);

        return $value === null ? null : mb_strtolower(preg_replace('/\s+/', '', $value) ?? $value);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }
}
