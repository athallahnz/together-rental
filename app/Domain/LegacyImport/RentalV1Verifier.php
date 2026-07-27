<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;
use App\Models\LegacyImportRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

final class RentalV1Verifier
{
    public function __construct(private readonly LegacyImportRecorder $recorder) {}

    public function verify(LegacyImportBatch $batch, int $userId): LegacyImportBatch
    {
        $this->guard($batch);
        $this->recorder->transition($batch, 'verifying', 'verification_started', $userId);

        try {
            $checks = [
                ...$this->tableCountChecks($batch),
                $this->targetExistenceCheck($batch),
                $this->activeRentalCheck($batch),
                $this->rentalAmountCheck($batch),
                $this->returnCountCheck($batch),
            ];
            $passed = collect($checks)->every(
                static fn (array $check): bool => $check['status'] === 'passed',
            );
            $summary = $batch->summary ?? [];
            $summary['verification'] = [
                'passed' => $passed,
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ];

            $batch->update([
                'status' => $passed ? 'verified' : 'executed',
                'summary' => $summary,
                'verified_at' => $passed ? now() : null,
                'failure_message' => $passed
                    ? null
                    : 'Verifikasi menemukan perbedaan. Periksa detail check sebelum mengulang.',
            ]);

            $this->recorder->event(
                $batch->fresh(),
                $passed ? 'verification_completed' : 'verification_failed',
                $userId,
                'verifying',
                $passed ? 'verified' : 'executed',
                ['passed' => $passed, 'checks' => count($checks)],
            );

            return $batch->fresh();
        } catch (Throwable $exception) {
            $this->recorder->fail($batch->fresh(), 'verification', $exception->getMessage(), $userId);

            throw $exception;
        }
    }

    private function guard(LegacyImportBatch $batch): void
    {
        $retry = $batch->status === 'failed'
            && ($batch->options['failed_step'] ?? null) === 'verification';

        if (! in_array($batch->status, ['executed', 'queued_verification'], true) && ! $retry) {
            throw new RuntimeException("Batch berstatus [{$batch->status}] belum siap diverifikasi.");
        }
    }

    /** @return list<array<string, mixed>> */
    private function tableCountChecks(LegacyImportBatch $batch): array
    {
        return DB::table('legacy_import_tables')
            ->where('batch_id', $batch->id)
            ->whereNotNull('target_table')
            ->orderBy('id')
            ->get()
            ->map(function (object $table) use ($batch): array {
                $expected = (int) $table->imported_rows;
                $actual = DB::table('legacy_import_rows')
                    ->where('batch_id', $batch->id)
                    ->where('source_table', $table->source_table)
                    ->where('status', 'imported')
                    ->whereNotNull('target_id')
                    ->count();

                return [
                    'code' => 'TABLE_IMPORTED_'.$table->source_table,
                    'label' => "Import {$table->source_table}",
                    'expected' => $expected,
                    'actual' => $actual,
                    'status' => $expected === $actual ? 'passed' : 'failed',
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function targetExistenceCheck(LegacyImportBatch $batch): array
    {
        $missing = 0;
        $checked = 0;
        $groups = DB::table('legacy_id_maps')
            ->where('batch_id', $batch->id)
            ->get()
            ->groupBy('target_table');

        foreach ($groups as $targetTable => $maps) {
            if (! Schema::hasTable((string) $targetTable)) {
                $missing += $maps->count();
                $checked += $maps->count();

                continue;
            }

            $targetIds = $maps->pluck('target_id')->map(static fn ($id): int => (int) $id)->all();
            $existing = 0;

            foreach (array_chunk($targetIds, 500) as $chunk) {
                $existing += DB::table((string) $targetTable)->whereIn('id', $chunk)->count();
            }

            $checked += count($targetIds);
            $missing += count($targetIds) - $existing;
        }

        return [
            'code' => 'ID_MAP_TARGETS_EXIST',
            'label' => 'Semua target ID map tersedia',
            'expected' => $checked,
            'actual' => $checked - $missing,
            'status' => $missing === 0 ? 'passed' : 'failed',
        ];
    }

    /** @return array<string, mixed> */
    private function activeRentalCheck(LegacyImportBatch $batch): array
    {
        $expected = 0;

        LegacyImportRow::query()
            ->where('batch_id', $batch->id)
            ->where('source_table', 'trx_rental')
            ->where('status', 'imported')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$expected): void {
                foreach ($rows as $row) {
                    if (RentalV1Value::rentalStatus($row->payload['rental_status'] ?? null) === 'active') {
                        $expected++;
                    }
                }
            });

        $actual = DB::table('legacy_id_maps')
            ->join('rentals', 'rentals.id', '=', 'legacy_id_maps.target_id')
            ->where('legacy_id_maps.batch_id', $batch->id)
            ->where('legacy_id_maps.source_table', 'trx_rental')
            ->where('legacy_id_maps.target_table', 'rentals')
            ->where('rentals.status', 'active')
            ->count();

        return [
            'code' => 'ACTIVE_RENTALS_RECONCILED',
            'label' => 'Rental aktif sesuai sumber',
            'expected' => $expected,
            'actual' => $actual,
            'status' => $expected === $actual ? 'passed' : 'failed',
        ];
    }

    /** @return array<string, mixed> */
    private function rentalAmountCheck(LegacyImportBatch $batch): array
    {
        $expected = 0.0;

        LegacyImportRow::query()
            ->where('batch_id', $batch->id)
            ->where('source_table', 'trx_rental')
            ->where('status', 'imported')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$expected): void {
                foreach ($rows as $row) {
                    $expected += (float) ($row->payload['rental_total_akhir'] ?? 0);
                }
            });

        $actual = (float) DB::table('legacy_id_maps')
            ->join('rentals', 'rentals.id', '=', 'legacy_id_maps.target_id')
            ->where('legacy_id_maps.batch_id', $batch->id)
            ->where('legacy_id_maps.source_table', 'trx_rental')
            ->where('legacy_id_maps.target_table', 'rentals')
            ->sum('rentals.total_amount');
        $difference = abs($expected - $actual);

        return [
            'code' => 'RENTAL_TOTAL_RECONCILED',
            'label' => 'Total nominal rental sesuai sumber',
            'expected' => number_format($expected, 2, '.', ''),
            'actual' => number_format($actual, 2, '.', ''),
            'difference' => number_format($difference, 2, '.', ''),
            'status' => $difference < 1 ? 'passed' : 'failed',
        ];
    }

    /** @return array<string, mixed> */
    private function returnCountCheck(LegacyImportBatch $batch): array
    {
        $expected = 0;

        LegacyImportRow::query()
            ->where('batch_id', $batch->id)
            ->where('source_table', 'trx_rental')
            ->where('status', 'imported')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$expected): void {
                foreach ($rows as $row) {
                    if (($row->payload['rental_status'] ?? null) === 'kembali') {
                        $expected++;
                    }
                }
            });

        $actual = DB::table('legacy_id_maps')
            ->where('batch_id', $batch->id)
            ->where('source_table', 'trx_rental_return')
            ->where('target_table', 'rental_returns')
            ->count();

        return [
            'code' => 'RETURNS_RECONCILED',
            'label' => 'Pengembalian rental terbentuk',
            'expected' => $expected,
            'actual' => $actual,
            'status' => $expected === $actual ? 'passed' : 'failed',
        ];
    }
}
