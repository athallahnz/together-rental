<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Documents\TransactionDocumentManager;
use App\Domain\Documents\TransactionDocumentPdfRenderer;
use App\Http\Requests\Documents\IssueTransactionDocumentRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\TransactionDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function sourceOptions(Request $request): JsonResponse
    {
        Gate::authorize('documents.issue');

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(['invoice', 'receipt', 'agreement'])],
            'source_type' => ['required', Rule::in(['booking', 'rental', 'payment'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $documentType = (string) $validated['document_type'];
        $sourceType = (string) $validated['source_type'];
        $search = trim((string) ($validated['q'] ?? ''));

        if (! $this->sourceTypeAllowed($documentType, $sourceType)) {
            throw ValidationException::withMessages([
                'source_type' => 'Sumber dokumen tidak sesuai dengan jenis dokumen.',
            ]);
        }

        $branchIds = $this->branches($request)->pluck('id');
        $rows = $this->sourceRows(
            documentType: $documentType,
            sourceType: $sourceType,
            branchIds: $branchIds,
            search: $search,
        );

        $sourceIds = $rows
            ->pluck('source_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values();

        $latestBySource = TransactionDocument::query()
            ->whereIn('branch_id', $branchIds)
            ->where('document_type', $documentType)
            ->where('source_type', $sourceType)
            ->whereIn('source_id', $sourceIds)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->get([
                'id',
                'source_id',
                'document_number',
                'version',
                'content_hash',
                'issued_at',
            ])
            ->groupBy('source_id')
            ->map(static fn (Collection $documents): ?TransactionDocument => $documents->first());

        $options = $rows
            ->map(function (array $row) use ($latestBySource): array {
                $latest = $latestBySource->get($row['source_id']);

                return [
                    ...$row,
                    'has_document' => $latest instanceof TransactionDocument,
                    'latest_version' => $latest instanceof TransactionDocument
                        ? (int) $latest->version
                        : null,
                    'latest_document_number' => $latest instanceof TransactionDocument
                        ? $latest->document_number
                        : null,
                    'latest_content_hash' => $latest instanceof TransactionDocument
                        ? $latest->content_hash
                        : null,
                ];
            })
            ->sort(function (array $left, array $right): int {
                if ($left['has_document'] !== $right['has_document']) {
                    return $left['has_document'] ? 1 : -1;
                }

                return ((int) $right['source_id']) <=> ((int) $left['source_id']);
            })
            ->values()
            ->take(100)
            ->values();

        return response()->json([
            'data' => $options->all(),
        ]);
    }

    public function quickIssue(
        IssueTransactionDocumentRequest $request,
        TransactionDocumentManager $manager,
        ActivityRecorder $recorder,
    ): JsonResponse {
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
                    'entry_point' => 'transaction-detail',
                ],
                $document->branch_id,
            );
        }

        return response()->json([
            'data' => [
                'id' => (int) $document->id,
                'document_number' => $document->document_number,
                'document_type' => $document->document_type,
                'version' => (int) $document->version,
                'created' => $document->wasRecentlyCreated,
                'pdf_url' => route('documents.pdf', $document),
                'preview_url' => route('documents.preview', $document),
            ],
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
        $document->loadMissing(['branch:id,code,name', 'issuer:id,name']);
        $filename = str($document->document_number)->lower()->replace(['/', '\\'], '-')->toString().'.pdf';

        return response($renderer->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function preview(
        Request $request,
        TransactionDocument $document,
        TransactionDocumentPdfRenderer $renderer,
    ): Response {
        Gate::authorize('documents.view');
        $this->guardAccess($request, $document);
        $document->loadMissing(['branch:id,code,name', 'issuer:id,name']);
        $filename = str($document->document_number)->lower()->replace(['/', '\\'], '-')->toString().'.pdf';

        return response($renderer->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
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

    private function sourceTypeAllowed(string $documentType, string $sourceType): bool
    {
        return match ($documentType) {
            'invoice' => in_array($sourceType, ['booking', 'rental'], true),
            'receipt' => $sourceType === 'payment',
            'agreement' => $sourceType === 'rental',
            default => false,
        };
    }

    /**
     * @param  Collection<int, int>  $branchIds
     * @return Collection<int, array{
     *     source_id: int,
     *     reference: string,
     *     branch_id: int,
     *     branch_code: string,
     *     branch_name: string,
     *     customer_name: string|null,
     *     status: string,
     *     amount: float
     * }>
     */
    private function sourceRows(
        string $documentType,
        string $sourceType,
        Collection $branchIds,
        string $search,
    ): Collection {
        return match ($sourceType) {
            'booking' => Booking::query()
                ->whereIn('branch_id', $branchIds)
                ->whereIn('status', ['confirmed', 'converted', 'completed'])
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('booking_number', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn (Builder $customer) => $customer
                                ->where('name', 'like', "%{$search}%"));
                    });
                })
                ->with([
                    'branch:id,code,name',
                    'customer:id,name',
                ])
                ->orderByDesc('id')
                ->limit(250)
                ->get([
                    'id',
                    'branch_id',
                    'customer_id',
                    'booking_number',
                    'status',
                    'total_amount',
                ])
                ->map(static fn (Booking $booking): array => [
                    'source_id' => (int) $booking->id,
                    'reference' => (string) $booking->booking_number,
                    'branch_id' => (int) $booking->branch_id,
                    'branch_code' => (string) ($booking->branch?->code ?? ''),
                    'branch_name' => (string) ($booking->branch?->name ?? ''),
                    'customer_name' => $booking->customer?->name,
                    'status' => (string) $booking->status,
                    'amount' => (float) $booking->total_amount,
                ]),
            'rental' => Rental::query()
                ->whereIn('branch_id', $branchIds)
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('rental_number', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn (Builder $customer) => $customer
                                ->where('name', 'like', "%{$search}%"));
                    });
                })
                ->with([
                    'branch:id,code,name',
                    'customer:id,name',
                ])
                ->orderByDesc('id')
                ->limit(250)
                ->get([
                    'id',
                    'branch_id',
                    'customer_id',
                    'rental_number',
                    'status',
                    'total_amount',
                ])
                ->map(static fn (Rental $rental): array => [
                    'source_id' => (int) $rental->id,
                    'reference' => (string) $rental->rental_number,
                    'branch_id' => (int) $rental->branch_id,
                    'branch_code' => (string) ($rental->branch?->code ?? ''),
                    'branch_name' => (string) ($rental->branch?->name ?? ''),
                    'customer_name' => $rental->customer?->name,
                    'status' => (string) $rental->status,
                    'amount' => (float) $rental->total_amount,
                ]),
            'payment' => Payment::query()
                ->whereIn('branch_id', $branchIds)
                ->where('status', 'completed')
                ->where('direction', 'in')
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('payment_number', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn (Builder $customer) => $customer
                                ->where('name', 'like', "%{$search}%"));
                    });
                })
                ->with([
                    'branch:id,code,name',
                    'customer:id,name',
                ])
                ->orderByDesc('id')
                ->limit(250)
                ->get([
                    'id',
                    'branch_id',
                    'customer_id',
                    'payment_number',
                    'status',
                    'amount',
                ])
                ->map(static fn (Payment $payment): array => [
                    'source_id' => (int) $payment->id,
                    'reference' => (string) $payment->payment_number,
                    'branch_id' => (int) $payment->branch_id,
                    'branch_code' => (string) ($payment->branch?->code ?? ''),
                    'branch_name' => (string) ($payment->branch?->name ?? ''),
                    'customer_name' => $payment->customer?->name,
                    'status' => (string) $payment->status,
                    'amount' => (float) $payment->amount,
                ]),
            default => collect(),
        };
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
