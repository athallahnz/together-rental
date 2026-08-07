<?php

namespace App\Http\Controllers;

use App\Domain\LegacyImport\LegacyImportRecorder;
use App\Domain\LegacyImport\LegacyImportTargetManager;
use App\Domain\LegacyImport\RentalV1Mapper;
use App\Http\Requests\StoreLegacyImportRequest;
use App\Http\Requests\UpdateLegacyImportTargetRequest;
use App\Jobs\RunLegacyImportStep;
use App\Models\Branch;
use App\Models\LegacyImportBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class LegacyImportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('imports.view');
        $user = $request->user();
        $branches = $this->branchOptions($user);
        $branchIds = $branches->pluck('id')->all();
        $batches = LegacyImportBatch::query()
            ->with(['branch:id,code,name,city', 'uploader:id,name,email'])
            ->when(
                $branchIds !== [],
                fn (Builder $query) => $query->whereIn('branch_id', $branchIds),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $firstBranch = $branches->first();
        $defaultBranchId = $branches->contains('id', $user->current_branch_id)
            ? $user->current_branch_id
            : ($firstBranch === null ? null : $firstBranch['id']);

        return Inertia::render('legacy-imports/index', [
            'branches' => $branches->values(),
            'defaultBranchId' => $defaultBranchId,
            'batches' => $batches,
            'maxUploadMegabytes' => round(
                (int) config('legacy-import.max_upload_kilobytes', 102400) / 1024,
            ),
            'permissions' => $this->permissionProps($request),
        ]);
    }

    public function store(
        StoreLegacyImportRequest $request,
        LegacyImportRecorder $recorder,
        LegacyImportTargetManager $targetManager,
    ): RedirectResponse {
        $user = $request->user();
        $branch = $this->resolveTargetBranch($user, (int) $request->validated('branch_id'));
        $prefix = (string) $request->validated('import_prefix');
        $targetManager->assertTargetIsValid($branch, $prefix);

        $file = $request->file('sql_file');
        $sha256 = hash_file('sha256', $file->getRealPath());
        $duplicate = LegacyImportBatch::query()
            ->where('source_system', (string) config('legacy-import.source_system', 'RentalV1'))
            ->where('source_sha256', $sha256)
            ->first(['id', 'source_city', 'import_prefix']);

        if ($duplicate !== null) {
            $duplicateTarget = trim((string) $duplicate->source_city) !== ''
                ? $duplicate->source_city.' ('.($duplicate->import_prefix ?: '---').')'
                : 'batch lama';

            throw ValidationException::withMessages([
                'sql_file' => "File yang sama sudah terdaftar pada batch {$duplicate->id} untuk {$duplicateTarget}. Upload ulang tidak diperlukan.",
            ]);
        }

        $batchId = (string) Str::uuid();
        $disk = (string) config('legacy-import.disk', 'local');
        $sourcePath = $file->storeAs("legacy-imports/{$batchId}", 'source.sql', $disk);
        $sourceCity = trim((string) $branch->city);

        try {
            $batch = LegacyImportBatch::query()->create([
                'id' => $batchId,
                'branch_id' => $branch->id,
                'source_city' => $sourceCity,
                'import_prefix' => $prefix,
                'uploaded_by' => $user->id,
                'source_system' => (string) config('legacy-import.source_system', 'RentalV1'),
                'source_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'source_path' => $sourcePath,
                'source_sha256' => $sha256,
                'source_size' => $file->getSize(),
                'status' => 'uploaded',
                'options' => [
                    'branch_code' => $branch->code,
                    'source_city' => $sourceCity,
                    'import_prefix' => $prefix,
                    'parser_mode' => 'allowlist_only',
                    'uploaded_sql_executed' => false,
                ],
            ]);

            $recorder->event($batch, 'uploaded', $user->id, null, 'uploaded', [
                'filename' => $batch->source_filename,
                'size' => $batch->source_size,
                'sha256' => $batch->source_sha256,
                'branch_id' => (int) $branch->id,
                'branch_code' => $branch->code,
                'source_city' => $sourceCity,
                'import_prefix' => $prefix,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($sourcePath);

            throw $exception;
        }

        return to_route('legacy-imports.show', $batch)->with('toast', [
            'type' => 'success',
            'message' => "File SQL RentalV1 untuk {$sourceCity} ({$prefix}) berhasil diunggah dan siap dipreview.",
        ]);
    }

    public function show(Request $request, LegacyImportBatch $legacyImport): Response
    {
        Gate::authorize('imports.view');
        $this->guardBranch($legacyImport, $request->user());

        $sourceTable = $request->string('source_table')->toString();
        $rowStatus = $request->string('row_status')->toString();
        $issueSeverity = $request->string('issue_severity')->toString();

        $rows = $legacyImport->rows()
            ->when($sourceTable !== '', fn ($query) => $query->where('source_table', $sourceTable))
            ->when($rowStatus !== '', fn ($query) => $query->where('status', $rowStatus))
            ->orderBy('source_table')
            ->orderBy('row_number')
            ->paginate(25, ['*'], 'rows_page')
            ->withQueryString();

        $issues = $legacyImport->issues()
            ->with('row:id,source_table,legacy_key,row_number')
            ->when($issueSeverity !== '', fn ($query) => $query->where('severity', $issueSeverity))
            ->orderByRaw("CASE severity WHEN 'error' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->latest('id')
            ->paginate(25, ['*'], 'issues_page')
            ->withQueryString();

        $legacyImport->load([
            'branch:id,code,name,city',
            'uploader:id,name,email',
            'tables' => fn ($query) => $query->orderBy('id'),
            'mappings' => fn ($query) => $query->orderBy('mapping_type')->orderBy('source_value'),
            'events' => fn ($query) => $query->latest('id')->limit(30),
        ]);

        return Inertia::render('legacy-imports/show', [
            'batch' => $legacyImport,
            'targetBranches' => $this->branchOptions($request->user(), $legacyImport)->values(),
            'rows' => $rows,
            'issues' => $issues,
            'issueSummary' => $legacyImport->issues()
                ->selectRaw('severity, status, COUNT(*) as aggregate')
                ->groupBy('severity', 'status')
                ->get(),
            'filters' => [
                'source_table' => $sourceTable,
                'row_status' => $rowStatus,
                'issue_severity' => $issueSeverity,
            ],
            'permissions' => $this->permissionProps($request),
        ]);
    }

    public function updateTarget(
        UpdateLegacyImportTargetRequest $request,
        LegacyImportBatch $legacyImport,
        LegacyImportTargetManager $targetManager,
    ): RedirectResponse {
        $user = $request->user();
        $this->guardBranch($legacyImport, $user);
        $branch = $this->resolveTargetBranch($user, (int) $request->validated('branch_id'));
        $beforeStatus = $legacyImport->status;
        $updated = $targetManager->updateTarget(
            $legacyImport,
            $branch,
            (string) $request->validated('import_prefix'),
            $user->id,
        );

        $reset = $beforeStatus !== 'uploaded' && $updated->status === 'uploaded';
        $message = $reset
            ? 'Tujuan import berhasil diubah. Preview, validasi, dan mapping lama telah direset agar PREFIX baru diterapkan dengan aman.'
            : "Tujuan import ditetapkan ke {$updated->source_city} dengan PREFIX {$updated->import_prefix}.";

        return back()->with('toast', [
            'type' => $reset ? 'warning' : 'success',
            'message' => $message,
        ]);
    }

    public function preview(
        Request $request,
        LegacyImportBatch $legacyImport,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('imports.validate');

        return $this->queueStep(
            $legacyImport,
            $request->user(),
            'preview',
            ['uploaded', 'previewed', 'failed'],
            $recorder,
        );
    }

    public function validateBatch(
        Request $request,
        LegacyImportBatch $legacyImport,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('imports.validate');

        return $this->queueStep(
            $legacyImport,
            $request->user(),
            'validation',
            ['previewed', 'validated', 'failed'],
            $recorder,
        );
    }

    public function mapBranch(
        Request $request,
        LegacyImportBatch $legacyImport,
        RentalV1Mapper $mapper,
    ): RedirectResponse {
        Gate::authorize('imports.validate');
        $legacyImport->loadMissing('branch');

        return $this->runStep(
            $legacyImport,
            $request->user(),
            fn () => $mapper->confirmBranch($legacyImport, $request->user()->id),
            "Mapping ke {$legacyImport->source_city} ({$legacyImport->import_prefix}) berhasil dikonfirmasi.",
        );
    }

    public function execute(
        Request $request,
        LegacyImportBatch $legacyImport,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('imports.execute');

        return $this->queueStep(
            $legacyImport,
            $request->user(),
            'execution',
            ['mapped', 'failed'],
            $recorder,
        );
    }

    public function verify(
        Request $request,
        LegacyImportBatch $legacyImport,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('imports.execute');

        return $this->queueStep(
            $legacyImport,
            $request->user(),
            'verification',
            ['executed', 'failed'],
            $recorder,
        );
    }

    /**
     * @param  list<string>  $allowedStatuses
     */
    private function queueStep(
        LegacyImportBatch $batch,
        User $user,
        string $step,
        array $allowedStatuses,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        $this->guardBranch($batch, $user);
        $failedStep = $batch->options['failed_step'] ?? null;

        if (
            ! in_array($batch->status, $allowedStatuses, true)
            || ($batch->status === 'failed' && $failedStep !== $step)
        ) {
            return to_route('legacy-imports.show', $batch)->with('toast', [
                'type' => 'error',
                'message' => "Batch berstatus [{$batch->status}] tidak dapat menjalankan {$step}.",
            ]);
        }

        if ($step === 'execution') {
            if (! preg_match('/^[A-Z]{3}$/', (string) $batch->import_prefix) || trim((string) $batch->source_city) === '') {
                return to_route('legacy-imports.show', $batch)->with('toast', [
                    'type' => 'error',
                    'message' => 'Execute diblokir sampai kota/cabang dan PREFIX 3 huruf dikonfirmasi.',
                ]);
            }

            if (! $this->mappingMatchesTarget($batch)) {
                return to_route('legacy-imports.show', $batch)->with('toast', [
                    'type' => 'error',
                    'message' => 'Execute diblokir karena mapping belum memakai kota/PREFIX terbaru. Simpan tujuan import lalu ulangi Preview, Validasi, dan Mapping.',
                ]);
            }
        }

        try {
            $recorder->transition(
                $batch,
                "queued_{$step}",
                "{$step}_queued",
                $user->id,
            );
            RunLegacyImportStep::dispatch($batch->id, $user->id, $step);

            return to_route('legacy-imports.show', $batch)->with('toast', [
                'type' => 'success',
                'message' => 'Proses masuk antrean dan akan berjalan di background.',
            ]);
        } catch (Throwable $exception) {
            $recorder->fail($batch->fresh(), $step, $exception->getMessage(), $user->id);
            report($exception);

            return to_route('legacy-imports.show', $batch)->with('toast', [
                'type' => 'error',
                'message' => 'Gagal memasukkan proses ke antrean: '.$exception->getMessage(),
            ]);
        }
    }

    /** @param callable(): mixed $operation */
    private function runStep(
        LegacyImportBatch $batch,
        User $user,
        callable $operation,
        string $successMessage,
    ): RedirectResponse {
        $this->guardBranch($batch, $user);

        try {
            $operation();

            return to_route('legacy-imports.show', $batch)->with('toast', [
                'type' => 'success',
                'message' => $successMessage,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return to_route('legacy-imports.show', $batch)->with('toast', [
                'type' => 'error',
                'message' => 'Proses gagal: '.$exception->getMessage(),
            ]);
        }
    }

    /** @return Collection<int, array{id: int, code: string, name: string, city: string, suggested_prefix: string, prefix_locked: bool}> */
    private function branchOptions(User $user, ?LegacyImportBatch $except = null): Collection
    {
        $branches = $this->importBranches($user)
            ->orderBy('city')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city']);
        $branchIds = $branches->pluck('id')->all();
        $establishedPrefixes = LegacyImportBatch::query()
            ->when(
                $branchIds !== [],
                fn (Builder $query) => $query->whereIn('branch_id', $branchIds),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
            )
            ->when(
                $except !== null,
                fn (Builder $query) => $query->where('id', '!=', $except->id),
            )
            ->whereNotNull('mapped_at')
            ->whereNotNull('import_prefix')
            ->latest('mapped_at')
            ->get(['branch_id', 'import_prefix'])
            ->unique('branch_id')
            ->keyBy('branch_id');

        return $branches->map(function (Branch $branch) use ($establishedPrefixes): array {
            $established = $establishedPrefixes->get($branch->id)?->import_prefix;
            $letters = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($branch->code))) ?? '';
            $suggested = is_string($established) && preg_match('/^[A-Z]{3}$/', $established)
                ? $established
                : (strlen($letters) >= 3 ? substr($letters, 0, 3) : '');

            return [
                'id' => (int) $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'city' => trim((string) $branch->city),
                'suggested_prefix' => $suggested,
                'prefix_locked' => is_string($established)
                    && preg_match('/^[A-Z]{3}$/', $established) === 1,
            ];
        });
    }

    /** @return Builder<Branch> */
    private function importBranches(User $user): Builder
    {
        $query = Branch::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->whereNotNull('city')
            ->where('city', '!=', '');

        if ($user->company_id === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasCompanyScopedRole()) {
            return $query;
        }

        if ($user->current_branch_id === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereKey($user->current_branch_id)
            ->whereHas('users', function ($branchUserQuery) use ($user): void {
                $branchUserQuery
                    ->where('users.id', $user->id)
                    ->where('branch_user.is_active', true);
            });
    }

    private function resolveTargetBranch(User $user, int $branchId): Branch
    {
        $branch = $this->importBranches($user)->whereKey($branchId)->first();

        if ($branch === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'Anda tidak memiliki akses aktif untuk menjadikan cabang tersebut sebagai tujuan import.',
            ]);
        }

        return $branch;
    }

    private function guardBranch(LegacyImportBatch $batch, User $user): void
    {
        abort_unless(
            $this->importBranches($user)->whereKey($batch->branch_id)->exists(),
            404,
        );
    }

    private function mappingMatchesTarget(LegacyImportBatch $batch): bool
    {
        $mapping = DB::table('legacy_import_mappings')
            ->where('batch_id', $batch->id)
            ->where('mapping_type', 'branch')
            ->where('is_confirmed', true)
            ->first(['target_id', 'transform_rule']);

        if ($mapping === null || $mapping->transform_rule === null) {
            return false;
        }

        try {
            $rule = json_decode((string) $mapping->transform_rule, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return (int) $mapping->target_id === (int) $batch->branch_id
            && ($rule['source_city'] ?? null) === $batch->source_city
            && ($rule['import_prefix'] ?? null) === $batch->import_prefix;
    }

    /** @return array<string, bool> */
    private function permissionProps(Request $request): array
    {
        return [
            'view' => $request->user()->can('imports.view'),
            'upload' => $request->user()->can('imports.upload'),
            'validate' => $request->user()->can('imports.validate'),
            'execute' => $request->user()->can('imports.execute'),
        ];
    }
}
