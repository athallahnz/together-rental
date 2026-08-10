<?php

namespace App\Domain\LegacyImport;

use App\Models\LegacyImportBatch;
use App\Models\LegacyImportIssue;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyImportIssueResolver
{
    public function __construct(private readonly LegacyImportRecorder $recorder) {}

    public function skipOrphanBookingDetails(LegacyImportBatch $batch, int $userId, string $reason): int
    {
        $this->guard($batch);

        return DB::transaction(function () use ($batch, $userId, $reason): int {
            $issues = $batch->issues()
                ->where('status', 'open')
                ->where('severity', 'error')
                ->where('code', 'REFERENCE_NOT_FOUND')
                ->where('field', 'bookingdet_booking_id')
                ->whereHas('row', fn ($query) => $query->where('source_table', 'trx_booking_detail'))
                ->lockForUpdate()
                ->get();

            if ($issues->isEmpty()) {
                throw new RuntimeException('Tidak ada detail booking yatim yang dapat diabaikan.');
            }

            $rowIds = $issues->pluck('legacy_import_row_id')->filter()->unique()->values();
            $now = now();

            LegacyImportIssue::query()->whereIn('id', $issues->modelKeys())->update([
                'status' => 'skipped',
                'resolved_by' => $userId,
                'resolved_at' => $now,
                'resolution_notes' => $reason,
                'updated_at' => $now,
            ]);

            DB::table('legacy_import_rows')->whereIn('id', $rowIds)->update([
                'status' => 'skipped',
                'updated_at' => $now,
            ]);

            $this->recount($batch);
            $this->recorder->event($batch->fresh(), 'orphan_booking_details_skipped', $userId, $batch->status, $batch->status, [
                'rows' => $rowIds->count(),
                'issues' => $issues->count(),
                'reason' => $reason,
            ]);

            return $rowIds->count();
        });
    }

    public function createMissingProductPlaceholder(LegacyImportBatch $batch, int $userId, string $reason): int
    {
        $this->guard($batch);

        return DB::transaction(function () use ($batch, $userId, $reason): int {
            $issues = $batch->issues()
                ->with('row')
                ->where('status', 'open')
                ->where('severity', 'error')
                ->where('code', 'REFERENCE_NOT_FOUND')
                ->where('field', 'bookingdet_rentproduct_id')
                ->whereHas('row', fn ($query) => $query->where('source_table', 'trx_booking_detail'))
                ->lockForUpdate()
                ->get();

            if ($issues->isEmpty()) {
                throw new RuntimeException('Tidak ada referensi produk booking yang perlu dibuatkan placeholder.');
            }

            $legacyIds = $issues->pluck('original_value')->map(fn ($value) => trim((string) $value))->filter()->unique();
            $now = now();

            foreach ($legacyIds as $legacyId) {
                $existing = DB::table('legacy_id_maps')
                    ->where('branch_id', $batch->branch_id)
                    ->where('source_system', $batch->source_system)
                    ->where('source_table', 'rent_product')
                    ->where('legacy_id', $legacyId)
                    ->value('target_id');

                if ($existing !== null) {
                    continue;
                }

                $companyId = DB::table('branches')->where('id', $batch->branch_id)->value('company_id');
                $sku = sprintf('LEG-%s-MISSING-%s', $batch->import_prefix ?: 'IMP', $legacyId);
                $productId = DB::table('products')->insertGetId([
                    'company_id' => $companyId,
                    'category_id' => null,
                    'sku' => mb_substr($sku, 0, 50),
                    'name' => mb_substr("Produk Legacy Tidak Dikenali #{$legacyId}", 0, 150),
                    'tracking_type' => 'quantity',
                    'description' => "Placeholder audit untuk referensi produk RentalV1 #{$legacyId}; tidak boleh disewakan.",
                    'replacement_value' => 0,
                    'is_rentable' => false,
                    'is_active' => false,
                    'metadata' => json_encode(['legacy_placeholder' => true, 'legacy_id' => $legacyId, 'batch_id' => $batch->id], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('legacy_id_maps')->insert([
                    'batch_id' => $batch->id,
                    'branch_id' => $batch->branch_id,
                    'source_system' => $batch->source_system,
                    'source_table' => 'rent_product',
                    'legacy_id' => $legacyId,
                    'target_table' => 'products',
                    'target_id' => $productId,
                    'metadata' => json_encode(['resolution' => 'missing_product_placeholder', 'reason' => $reason], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            LegacyImportIssue::query()->whereIn('id', $issues->modelKeys())->update([
                'status' => 'resolved',
                'resolved_by' => $userId,
                'resolved_at' => $now,
                'resolution_notes' => $reason,
                'updated_at' => $now,
            ]);

            $this->recount($batch);
            $this->recorder->event($batch->fresh(), 'missing_products_resolved', $userId, $batch->status, $batch->status, [
                'legacy_ids' => $legacyIds->values()->all(),
                'issues' => $issues->count(),
                'reason' => $reason,
            ]);

            return $legacyIds->count();
        });
    }

    private function guard(LegacyImportBatch $batch): void
    {
        if ($batch->status !== 'validated' || $batch->executed_at !== null) {
            throw new RuntimeException('Isu hanya dapat diselesaikan setelah validasi dan sebelum execute.');
        }
    }

    private function recount(LegacyImportBatch $batch): void
    {
        $rows = DB::table('legacy_import_rows')->where('batch_id', $batch->id)->get(['id', 'source_table', 'status']);

        foreach ($rows->whereNotIn('status', ['skipped', 'imported']) as $row) {
            $openSeverities = DB::table('legacy_import_issues')
                ->where('legacy_import_row_id', $row->id)
                ->where('status', 'open')
                ->pluck('severity');
            $status = $openSeverities->contains('error') ? 'error' : ($openSeverities->isEmpty() ? 'valid' : 'warning');
            DB::table('legacy_import_rows')->where('id', $row->id)->update(['status' => $status, 'updated_at' => now()]);
        }

        $counts = DB::table('legacy_import_rows')->where('batch_id', $batch->id)
            ->selectRaw('status, COUNT(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');

        $batch->update([
            'valid_rows' => (int) ($counts['valid'] ?? 0),
            'warning_rows' => (int) ($counts['warning'] ?? 0),
            'error_rows' => (int) ($counts['error'] ?? 0),
            'skipped_rows' => (int) ($counts['skipped'] ?? 0),
        ]);

        foreach ($rows->pluck('source_table')->unique() as $sourceTable) {
            $tableCounts = DB::table('legacy_import_rows')->where('batch_id', $batch->id)->where('source_table', $sourceTable)
                ->selectRaw('status, COUNT(*) aggregate')->groupBy('status')->pluck('aggregate', 'status');
            DB::table('legacy_import_tables')->where('batch_id', $batch->id)->where('source_table', $sourceTable)->update([
                'status' => isset($tableCounts['skipped']) && count($tableCounts) === 1 ? 'excluded' : 'validated',
                'valid_rows' => (int) ($tableCounts['valid'] ?? 0),
                'warning_rows' => (int) ($tableCounts['warning'] ?? 0),
                'error_rows' => (int) ($tableCounts['error'] ?? 0),
                'updated_at' => now(),
            ]);
        }
    }
}
