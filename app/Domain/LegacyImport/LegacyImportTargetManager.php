<?php

namespace App\Domain\LegacyImport;

use App\Models\Branch;
use App\Models\LegacyImportBatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class LegacyImportTargetManager
{
    public function __construct(private readonly LegacyImportRecorder $recorder) {}

    public function assertTargetIsValid(Branch $branch, string $prefix, ?LegacyImportBatch $except = null): void
    {
        $prefix = strtoupper(trim($prefix));

        if (! preg_match('/^[A-Z]{3}$/', $prefix)) {
            throw ValidationException::withMessages([
                'import_prefix' => 'PREFIX import wajib tepat 3 huruf A-Z.',
            ]);
        }

        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang tujuan import harus berstatus aktif.',
            ]);
        }

        if (trim((string) $branch->city) === '') {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang tujuan belum memiliki nama kota. Lengkapi data kota pada master Cabang terlebih dahulu.',
            ]);
        }

        $bindingQuery = LegacyImportBatch::query()
            ->whereHas('branch', fn ($query) => $query->where('company_id', $branch->company_id))
            ->whereNotNull('mapped_at')
            ->whereNotNull('import_prefix');

        if ($except !== null) {
            $bindingQuery->where('id', '!=', $except->getKey());
        }

        $prefixOwner = (clone $bindingQuery)
            ->where('import_prefix', $prefix)
            ->where('branch_id', '!=', $branch->id)
            ->with('branch:id,code,name,city')
            ->latest('mapped_at')
            ->first();

        if ($prefixOwner !== null) {
            $owner = $prefixOwner->branch?->city
                ?: $prefixOwner->branch?->name
                ?: $prefixOwner->branch?->code
                ?: 'cabang lain';

            throw ValidationException::withMessages([
                'import_prefix' => "PREFIX {$prefix} sudah terikat pada {$owner}. Gunakan PREFIX lain untuk cabang ini.",
            ]);
        }

        $branchBinding = (clone $bindingQuery)
            ->where('branch_id', $branch->id)
            ->where('import_prefix', '!=', $prefix)
            ->latest('mapped_at')
            ->first(['id', 'import_prefix']);

        if ($branchBinding !== null) {
            throw ValidationException::withMessages([
                'import_prefix' => "Cabang {$branch->code} sudah menggunakan PREFIX {$branchBinding->import_prefix} pada import sebelumnya. Gunakan PREFIX yang sama agar identitas legacy konsisten.",
            ]);
        }
    }

    public function updateTarget(
        LegacyImportBatch $batch,
        Branch $branch,
        string $prefix,
        int $userId,
    ): LegacyImportBatch {
        $this->guardEditable($batch);
        $this->assertTargetIsValid($branch, $prefix, $batch);

        $prefix = strtoupper(trim($prefix));
        $sourceCity = trim((string) $branch->city);
        $options = $batch->options ?? [];
        $identitySnapshotIsCurrent = ($options['import_prefix'] ?? null) === $prefix
            && ($options['source_city'] ?? null) === $sourceCity
            && ($options['branch_code'] ?? null) === $branch->code;
        $changed = (int) $batch->branch_id !== (int) $branch->id
            || $batch->import_prefix !== $prefix
            || $batch->source_city !== $sourceCity
            || ! $identitySnapshotIsCurrent;

        if (! $changed) {
            $batch->refresh()->load('branch');

            return $batch;
        }

        $fromStatus = $batch->status;
        $oldIdentity = [
            'branch_id' => (int) $batch->branch_id,
            'source_city' => $batch->source_city,
            'import_prefix' => $batch->import_prefix,
        ];
        $needsReset = $this->hasDerivedState($batch);

        DB::transaction(function () use (
            $batch,
            $branch,
            $prefix,
            $sourceCity,
            $needsReset,
        ): void {
            if ($needsReset) {
                DB::table('legacy_import_issues')->where('batch_id', $batch->id)->delete();
                DB::table('legacy_import_mappings')->where('batch_id', $batch->id)->delete();
                DB::table('legacy_import_rows')->where('batch_id', $batch->id)->delete();
                DB::table('legacy_import_tables')->where('batch_id', $batch->id)->delete();
            }

            $options = $batch->options ?? [];
            unset($options['failed_step']);
            $options['branch_code'] = $branch->code;
            $options['import_prefix'] = $prefix;
            $options['source_city'] = $sourceCity;

            $updates = [
                'branch_id' => $branch->id,
                'source_city' => $sourceCity,
                'import_prefix' => $prefix,
                'options' => $options,
                'failure_message' => null,
                'failed_at' => null,
            ];

            if ($needsReset) {
                $updates = [
                    ...$updates,
                    'status' => 'uploaded',
                    'total_rows' => 0,
                    'valid_rows' => 0,
                    'warning_rows' => 0,
                    'error_rows' => 0,
                    'imported_rows' => 0,
                    'skipped_rows' => 0,
                    'summary' => null,
                    'previewed_at' => null,
                    'validated_at' => null,
                    'mapped_at' => null,
                ];
            }

            $batch->update($updates);
        });

        $batch->refresh()->load('branch');
        $fresh = $batch;
        $this->recorder->event(
            $fresh,
            $needsReset ? 'target_changed_preview_reset' : 'target_changed',
            $userId,
            $fromStatus,
            $fresh->status,
            [
                'previous' => $oldIdentity,
                'current' => [
                    'branch_id' => (int) $fresh->branch_id,
                    'source_city' => $fresh->source_city,
                    'import_prefix' => $fresh->import_prefix,
                ],
            ],
        );

        return $fresh;
    }

    private function guardEditable(LegacyImportBatch $batch): void
    {
        if (
            str_starts_with($batch->status, 'queued_')
            || in_array($batch->status, ['previewing', 'validating', 'executing', 'verifying'], true)
        ) {
            throw ValidationException::withMessages([
                'branch_id' => 'Tunggu proses import yang sedang berjalan selesai sebelum mengubah kota/cabang atau PREFIX.',
            ]);
        }

        $failedStep = $batch->options['failed_step'] ?? null;
        $executionStarted = $batch->executed_at !== null
            || in_array($batch->status, [
                'queued_execution',
                'executing',
                'executed',
                'queued_verification',
                'verifying',
                'verified',
            ], true)
            || ($batch->status === 'failed' && in_array($failedStep, ['execution', 'verification'], true));

        if ($executionStarted) {
            throw ValidationException::withMessages([
                'branch_id' => 'Kota/cabang dan PREFIX tidak dapat diubah setelah proses Execute dimulai.',
            ]);
        }
    }

    private function hasDerivedState(LegacyImportBatch $batch): bool
    {
        if ($batch->previewed_at !== null || $batch->validated_at !== null || $batch->mapped_at !== null) {
            return true;
        }

        if ($batch->status === 'failed') {
            return in_array($batch->options['failed_step'] ?? null, ['preview', 'validation', 'mapping'], true);
        }

        return ! in_array($batch->status, ['uploaded'], true);
    }
}
