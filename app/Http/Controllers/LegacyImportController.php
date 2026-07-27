<?php

namespace App\Http\Controllers;

use App\Domain\LegacyImport\LegacyImportRecorder;
use App\Domain\LegacyImport\RentalV1Mapper;
use App\Http\Requests\StoreLegacyImportRequest;
use App\Jobs\RunLegacyImportStep;
use App\Models\Branch;
use App\Models\LegacyImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $branch = $this->targetBranch();
        $batches = LegacyImportBatch::query()
            ->with(['branch:id,code,name', 'uploader:id,name,email'])
            ->where('branch_id', $branch->id)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('legacy-imports/index', [
            'branch' => $branch->only(['id', 'code', 'name']),
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
    ): RedirectResponse {
        $file = $request->file('sql_file');
        $sha256 = hash_file('sha256', $file->getRealPath());
        $duplicate = LegacyImportBatch::query()
            ->where('source_system', (string) config('legacy-import.source_system', 'RentalV1'))
            ->where('source_sha256', $sha256)
            ->first();

        if ($duplicate !== null) {
            throw ValidationException::withMessages([
                'sql_file' => "File yang sama sudah terdaftar pada batch {$duplicate->id}.",
            ]);
        }

        $branch = $this->targetBranch();
        $batchId = (string) Str::uuid();
        $disk = (string) config('legacy-import.disk', 'local');
        $sourcePath = $file->storeAs("legacy-imports/{$batchId}", 'source.sql', $disk);

        try {
            $batch = LegacyImportBatch::query()->create([
                'id' => $batchId,
                'branch_id' => $branch->id,
                'uploaded_by' => $request->user()->id,
                'source_system' => (string) config('legacy-import.source_system', 'RentalV1'),
                'source_filename' => mb_substr($file->getClientOriginalName(), 0, 255),
                'source_path' => $sourcePath,
                'source_sha256' => $sha256,
                'source_size' => $file->getSize(),
                'status' => 'uploaded',
                'options' => [
                    'branch_code' => $branch->code,
                    'parser_mode' => 'allowlist_only',
                    'uploaded_sql_executed' => false,
                ],
            ]);

            $recorder->event($batch, 'uploaded', $request->user()->id, null, 'uploaded', [
                'filename' => $batch->source_filename,
                'size' => $batch->source_size,
                'sha256' => $batch->source_sha256,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($sourcePath);

            throw $exception;
        }

        return to_route('legacy-imports.show', $batch)->with('toast', [
            'type' => 'success',
            'message' => 'File SQL RentalV1 berhasil diunggah dan siap dipreview.',
        ]);
    }

    public function show(Request $request, LegacyImportBatch $legacyImport): Response
    {
        Gate::authorize('imports.view');
        $this->guardBranch($legacyImport);

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
            'branch:id,code,name',
            'uploader:id,name,email',
            'tables' => fn ($query) => $query->orderBy('id'),
            'mappings' => fn ($query) => $query->orderBy('mapping_type')->orderBy('source_value'),
            'events' => fn ($query) => $query->latest('id')->limit(30),
        ]);

        return Inertia::render('legacy-imports/show', [
            'batch' => $legacyImport,
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

    public function preview(
        Request $request,
        LegacyImportBatch $legacyImport,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('imports.validate');

        return $this->queueStep(
            $legacyImport,
            $request->user()->id,
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
            $request->user()->id,
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

        return $this->runStep(
            $legacyImport,
            fn () => $mapper->confirmBranch($legacyImport, $request->user()->id),
            "Mapping ke cabang {$legacyImport->branch->code} berhasil dikonfirmasi.",
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
            $request->user()->id,
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
            $request->user()->id,
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
        int $userId,
        string $step,
        array $allowedStatuses,
        LegacyImportRecorder $recorder,
    ): RedirectResponse {
        $this->guardBranch($batch);
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

        try {
            $recorder->transition(
                $batch,
                "queued_{$step}",
                "{$step}_queued",
                $userId,
            );
            RunLegacyImportStep::dispatch($batch->id, $userId, $step);

            return to_route('legacy-imports.show', $batch)->with('toast', [
                'type' => 'success',
                'message' => 'Proses masuk antrean dan akan berjalan di background.',
            ]);
        } catch (Throwable $exception) {
            $recorder->fail($batch->fresh(), $step, $exception->getMessage(), $userId);
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
        callable $operation,
        string $successMessage,
    ): RedirectResponse {
        $this->guardBranch($batch);

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

    private function targetBranch(): Branch
    {
        return Branch::query()
            ->where('code', (string) config('legacy-import.branch_code', 'PNG'))
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function guardBranch(LegacyImportBatch $batch): void
    {
        abort_unless($batch->branch_id === $this->targetBranch()->id, 404);
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
