<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class RentalV1Mapper
{
    public function __construct(private readonly LegacyImportRecorder $recorder) {}

    public function confirmBranch(LegacyImportBatch $batch, int $userId): LegacyImportBatch
    {
        $this->guard($batch);
        $branchCode = (string) config('legacy-import.branch_code', 'PNG');
        $branch = DB::table('branches')->where('code', $branchCode)->first();

        if ($branch === null || (int) $branch->id !== (int) $batch->branch_id) {
            throw new RuntimeException(
                "Cabang tujuan [{$branchCode}] tidak tersedia atau tidak cocok dengan batch.",
            );
        }

        try {
            DB::transaction(function () use ($batch, $branch, $branchCode, $userId): void {
                DB::table('legacy_import_mappings')->where('batch_id', $batch->id)->delete();

                $now = now();
                $rows = [
                    $this->mapping($batch, 'branch', (string) $branch->name, 'branches', (int) $branch->id, [
                        'branch_code' => $branchCode,
                    ], $userId, $now),
                    $this->mapping($batch, 'gender', 'Laki-laki', null, null, ['value' => 'male'], $userId, $now),
                    $this->mapping($batch, 'gender', 'Perempuan', null, null, ['value' => 'female'], $userId, $now),
                    $this->mapping($batch, 'gender', '__NULL__', null, null, ['value' => null], $userId, $now),
                    $this->mapping($batch, 'booking_status', 'pesan', null, null, ['value' => 'confirmed'], $userId, $now),
                    $this->mapping($batch, 'booking_status', 'pinjam', null, null, ['value' => 'converted'], $userId, $now),
                    $this->mapping($batch, 'booking_status', 'batal', null, null, ['value' => 'cancelled'], $userId, $now),
                    $this->mapping($batch, 'rental_status', 'pinjam', null, null, ['value' => 'active'], $userId, $now),
                    $this->mapping($batch, 'rental_status', 'perpanjangan', null, null, ['value' => 'active'], $userId, $now),
                    $this->mapping($batch, 'rental_status', 'kembali', null, null, ['value' => 'returned'], $userId, $now),
                ];

                foreach (['0' => '6H', '1' => '12H', '2' => '1D'] as $legacyType => $rateCode) {
                    $ratePlanId = DB::table('rate_plans')
                        ->where('company_id', $branch->company_id)
                        ->where('code', $rateCode)
                        ->value('id');

                    if ($ratePlanId === null) {
                        throw new RuntimeException("Rate plan [{$rateCode}] belum tersedia.");
                    }

                    $rows[] = $this->mapping(
                        $batch,
                        'rental_type',
                        $legacyType,
                        'rate_plans',
                        (int) $ratePlanId,
                        ['code' => $rateCode],
                        $userId,
                        $now,
                    );
                }

                DB::table('legacy_import_mappings')->insert($rows);
            });

            $options = $batch->options ?? [];
            unset($options['failed_step']);

            $batch->update([
                'status' => 'mapped',
                'options' => $options,
                'failure_message' => null,
                'mapped_at' => now(),
                'failed_at' => null,
            ]);

            $this->recorder->event(
                $batch->fresh(),
                'mapping_confirmed',
                $userId,
                'validated',
                'mapped',
                ['branch_code' => $branchCode],
            );

            return $batch->fresh();
        } catch (Throwable $exception) {
            $this->recorder->fail($batch->fresh(), 'mapping', $exception->getMessage(), $userId);

            throw $exception;
        }
    }

    private function guard(LegacyImportBatch $batch): void
    {
        $retry = $batch->status === 'failed'
            && ($batch->options['failed_step'] ?? null) === 'mapping';

        if ($batch->status !== 'validated' && ! $retry) {
            throw new RuntimeException("Batch berstatus [{$batch->status}] belum siap dipetakan.");
        }

        if ($batch->error_rows > 0) {
            throw new RuntimeException('Mapping diblokir karena masih ada baris error.');
        }
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    private function mapping(
        LegacyImportBatch $batch,
        string $type,
        string $sourceValue,
        ?string $targetTable,
        ?int $targetId,
        array $rule,
        int $userId,
        mixed $now,
    ): array {
        return [
            'batch_id' => $batch->id,
            'mapping_type' => $type,
            'source_value' => $sourceValue,
            'target_table' => $targetTable,
            'target_id' => $targetId,
            'transform_rule' => json_encode($rule, JSON_THROW_ON_ERROR),
            'is_confirmed' => true,
            'confirmed_by' => $userId,
            'confirmed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
