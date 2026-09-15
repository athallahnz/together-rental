<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Documents\TransactionDocumentManager;
use App\Domain\Documents\TransactionDocumentPdfRenderer;
use App\Http\Requests\Documents\IssueTransactionDocumentRequest;
use App\Models\Branch;
use App\Models\TransactionDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

class TransactionDocumentController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('documents.view');
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'document_type' => ['nullable', Rule::in(['invoice', 'receipt', 'agreement'])],
            'source_type' => ['nullable', Rule::in(['booking', 'rental', 'payment'])],
        ]);
        $actor = $request->user();
        $branches = $this->branches($request);
        $branchIds = $branches->pluck('id');
        $search = trim((string) ($validated['search'] ?? ''));
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $documentType = (string) ($validated['document_type'] ?? '');
        $sourceType = (string) ($validated['source_type'] ?? '');

        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }

        $scope = TransactionDocument::query()
            ->whereIn('branch_id', $branchIds)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($documentType !== '', fn (Builder $query) => $query->where('document_type', $documentType))
            ->when($sourceType !== '', fn (Builder $query) => $query->where('source_type', $sourceType))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('document_number', 'like', "%{$search}%")
                        ->orWhere('source_reference', 'like', "%{$search}%");
                });
            });

        $documents = (clone $scope)
            ->with(['branch:id,code,name', 'issuer:id,name'])
            ->latest('issued_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (TransactionDocument $document): array => [
                ...$document->toArray(),
                'pdf_url' => route('documents.pdf', $document),
                'source_url' => $this->sourceUrl($document),
            ]);

        return Inertia::render('documents/index', [
            'documents' => $documents,
            'summary' => [
                'total' => (clone $scope)->count(),
                'invoice' => (clone $scope)->where('document_type', 'invoice')->count(),
                'receipt' => (clone $scope)->where('document_type', 'receipt')->count(),
                'agreement' => (clone $scope)->where('document_type', 'agreement')->count(),
            ],
            'branches' => $branches,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'document_type' => $documentType,
                'source_type' => $sourceType,
            ],
            'canIssue' => $actor->can('documents.issue'),
        ]);
    }

    public function issue(
        IssueTransactionDocumentRequest $request,
        TransactionDocumentManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $document = $manager->issue(
            (string) $request->validated('document_type'),
            (string) $request->validated('source_type'),
            (string) $request->validated('source_reference'),
            $request->user(),
        );

        if ($document->wasRecentlyCreated) {
            $recorder->record(
                $request,
                'transaction-document.issued',
                $document,
                null,
                [
                    'document_number' => $document->document_number,
                    'document_type' => $document->document_type,
                    'source_type' => $document->source_type,
                    'source_reference' => $document->source_reference,
                    'version' => $document->version,
                    'content_hash' => $document->content_hash,
                ],
                $document->branch_id,
            );
        }

        return to_route('documents.index')->with('toast', [
            'type' => 'success',
            'message' => $document->wasRecentlyCreated
                ? "Dokumen {$document->document_number} berhasil diterbitkan."
                : "Snapshot belum berubah. Dokumen {$document->document_number} digunakan kembali.",
        ]);
    }

    public function pdf(
        Request $request,
        TransactionDocument $document,
        TransactionDocumentPdfRenderer $renderer,
    ): Response {
        Gate::authorize('documents.view');
        $this->guardAccess($request, $document);
        $document->loadMissing('branch:id,code,name');
        $filename = str($document->document_number)->lower()->replace(['/', '\\'], '-')->toString().'.pdf';

        return response($renderer->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function guardAccess(Request $request, TransactionDocument $document): void
    {
        abort_unless(
            $this->branches($request)->contains('id', $document->branch_id),
            404,
        );
    }

    /** @return Collection<int, Branch> */
    private function branches(Request $request): Collection
    {
        $actor = $request->user();

        $query = $actor->accessibleBranches();
        if (! $actor->hasCompanyScopedRole()) {
            $query->whereKey($actor->current_branch_id ?? 0);
        }

        return $query
            ->orderBy('name')
            ->get(['branches.id', 'branches.code', 'branches.name']);
    }

    private function sourceUrl(TransactionDocument $document): ?string
    {
        return match ($document->source_type) {
            'booking' => route('bookings.show', $document->source_id),
            'rental' => route('rentals.show', $document->source_id),
            'payment' => route('finance.payments.show', $document->source_id),
            default => null,
        };
    }
}
