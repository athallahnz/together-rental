<?php

namespace App\Http\Controllers\Transfers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Transfers\BranchTransferManager;
use App\Domain\Transfers\Enums\ApprovalDecision;
use App\Domain\Transfers\Enums\ApprovalSide;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Domain\Transfers\TransferApprovalManager;
use App\Domain\Transfers\TransferEligibilityService;
use App\Domain\Transfers\TransferSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\CancelBranchTransferRequest;
use App\Http\Requests\Transfers\PreflightBranchTransferRequest;
use App\Http\Requests\Transfers\SaveBranchTransferRequest;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BranchTransferController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('transfers.view');
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $accessibleIds = $user->accessibleBranches()->pluck('id');
        $base = BranchTransfer::query()
            ->where('company_id', $user->company_id)
            ->where(function (Builder $query) use ($accessibleIds): void {
                $query->whereIn('from_branch_id', $accessibleIds)
                    ->orWhereIn('to_branch_id', $accessibleIds);
            });

        return Inertia::render('transfers/index', [
            'transfers' => (clone $base)
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('transfer_number', 'like', "%{$search}%")
                            ->orWhereHas('items.asset', fn (Builder $asset) => $asset
                                ->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%"))
                            ->orWhereHas('items.product', fn (Builder $product) => $product
                                ->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when(
                    in_array($status, array_column(TransferStatus::cases(), 'value'), true),
                    fn (Builder $query) => $query->where('status', $status),
                )
                ->when($branchId !== null, fn (Builder $query) => $query->where(
                    fn (Builder $nested) => $nested
                        ->where('from_branch_id', $branchId)
                        ->orWhere('to_branch_id', $branchId),
                ))
                ->with([
                    'originBranch:id,code,name',
                    'destinationBranch:id,code,name',
                    'requester:id,name',
                ])
                ->withCount('items')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'summary' => [
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'pending_approval' => (clone $base)->where('status', 'pending_approval')->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'in_transit' => (clone $base)->whereIn('status', ['dispatched', 'receiving'])->count(),
                'discrepancy' => (clone $base)->where('status', 'discrepancy')->count(),
            ],
            'branches' => $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => compact('search', 'status', 'branchId'),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('transfers.create');

        return Inertia::render('transfers/form', [
            'transfer' => null,
            'branches' => Branch::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'currentBranchId' => $request->user()->current_branch_id,
        ]);
    }

    public function store(
        SaveBranchTransferRequest $request,
        BranchTransferManager $manager,
        TransferApprovalManager $approvals,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $submitted = $request->boolean('submit');
        if ($submitted) {
            Gate::authorize('transfers.approve');
        }

        $transfer = DB::transaction(function () use ($request, $manager, $approvals, $submitted): BranchTransfer {
            $created = $manager->createDraft($request->validated(), $request->user());

            if (! $submitted) {
                return $created;
            }

            $created = $manager->submit($created, $request->user());
            $side = $request->user()->current_branch_id === $created->from_branch_id
                ? ApprovalSide::Origin
                : ApprovalSide::Destination;

            return $approvals->decide(
                $created,
                $side,
                ApprovalDecision::Approved,
                $request->validated('approval_notes'),
                $request->user(),
            );
        }, 3);

        $recorder->record($request, 'transfer.created', $transfer, null, [
            'status' => $transfer->status->value,
            'revision_number' => $transfer->revision_number,
            'item_count' => $transfer->items->count(),
        ], $request->user()->current_branch_id);

        if ($submitted) {
            $side = $request->user()->current_branch_id === $transfer->from_branch_id
                ? ApprovalSide::Origin
                : ApprovalSide::Destination;
            $recorder->record($request, 'transfer.submitted', $transfer, null, [
                'status' => $transfer->status->value,
                'revision_number' => $transfer->revision_number,
                'approval_side' => $side->value,
            ], $request->user()->current_branch_id);
        }

        return to_route('transfers.show', $transfer)->with('toast', [
            'type' => 'success',
            'message' => $submitted
                ? 'Transfer berhasil diajukan dan persetujuan cabang aktif telah dicatat.'
                : 'Draft transfer berhasil disimpan.',
        ]);
    }

    public function show(Request $request, BranchTransfer $transfer, TransferSettings $settings): Response
    {
        Gate::authorize('transfers.view');
        $this->guardAccess($request, $transfer);
        $transfer->load([
            'originBranch:id,code,name',
            'destinationBranch:id,code,name',
            'requester:id,name',
            'approver:id,name',
            'shipper:id,name',
            'receiver:id,name',
            'items.product:id,sku,name,tracking_type',
            'items.asset:id,product_id,current_branch_id,asset_code,serial_number,status,condition',
            'items.inspections.media',
            'approvals.branch:id,code,name',
            'approvals.decider:id,name',
            'expenses.expenseBranch:id,code,name',
            'expenses.payment:id,payment_number,status,amount,paid_at',
            'expenses.documents',
            'documents.uploader:id,name',
            'statusHistories.changer:id,name',
        ]);

        $companyId = $request->user()->company_id;
        $currentBranchId = $request->user()->current_branch_id;

        return Inertia::render('transfers/show', [
            'transfer' => $transfer,
            'dispatchSettings' => $settings->dispatch($transfer->from_branch_id),
            'receivingSettings' => $settings->receiving($transfer->to_branch_id),
            'financialCategories' => DB::table('financial_categories')
                ->where('company_id', $companyId)
                ->where('type', 'expense')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'paymentMethods' => DB::table('payment_methods')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'type', 'requires_reference']),
            'cashSessions' => DB::table('cash_sessions')
                ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
                ->where('cash_registers.branch_id', $currentBranchId)
                ->where('cash_sessions.status', 'open')
                ->get([
                    'cash_sessions.id',
                    'cash_registers.name as register_name',
                    'cash_sessions.opened_at',
                ]),
            'permissions' => $this->permissions($request),
            'currentBranchId' => $currentBranchId,
        ]);
    }

    public function edit(Request $request, BranchTransfer $transfer): Response
    {
        Gate::authorize('transfers.update');
        $this->guardAccess($request, $transfer);
        $transfer->load([
            'items.product:id,sku,name,tracking_type',
            'items.asset:id,product_id,asset_code,serial_number,status,condition',
        ]);

        return Inertia::render('transfers/form', [
            'transfer' => $transfer,
            'branches' => Branch::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'currentBranchId' => $request->user()->current_branch_id,
        ]);
    }

    public function update(
        SaveBranchTransferRequest $request,
        BranchTransfer $transfer,
        BranchTransferManager $manager,
        TransferApprovalManager $approvals,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $transfer);
        $before = $transfer->only(['status', 'revision_number', 'lock_version']);
        $submitted = $request->boolean('submit');
        if ($submitted) {
            Gate::authorize('transfers.approve');
        }

        $transfer = DB::transaction(function () use (
            $request,
            $transfer,
            $manager,
            $approvals,
            $submitted,
        ): BranchTransfer {
            $updated = $manager->update($transfer, $request->validated(), $request->user());

            if (! $submitted) {
                return $updated;
            }

            $updated = $manager->submit($updated, $request->user());
            $side = $request->user()->current_branch_id === $updated->from_branch_id
                ? ApprovalSide::Origin
                : ApprovalSide::Destination;

            return $approvals->decide(
                $updated,
                $side,
                ApprovalDecision::Approved,
                $request->validated('approval_notes'),
                $request->user(),
            );
        }, 3);

        $recorder->record($request, 'transfer.updated', $transfer, $before, [
            'status' => $transfer->status->value,
            'revision_number' => $transfer->revision_number,
            'lock_version' => $transfer->lock_version,
        ], $request->user()->current_branch_id);

        return to_route('transfers.show', $transfer)->with('toast', [
            'type' => 'success',
            'message' => 'Transfer berhasil diperbarui.',
        ]);
    }

    public function submit(
        Request $request,
        BranchTransfer $transfer,
        BranchTransferManager $manager,
        TransferApprovalManager $approvals,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('transfers.create');
        Gate::authorize('transfers.approve');
        $this->guardAccess($request, $transfer);
        $transfer = DB::transaction(function () use (
            $request,
            $transfer,
            $manager,
            $approvals,
        ): BranchTransfer {
            $submitted = $manager->submit($transfer, $request->user());
            $side = $request->user()->current_branch_id === $submitted->from_branch_id
                ? ApprovalSide::Origin
                : ApprovalSide::Destination;

            return $approvals->decide(
                $submitted,
                $side,
                ApprovalDecision::Approved,
                null,
                $request->user(),
            );
        }, 3);
        $side = $request->user()->current_branch_id === $transfer->from_branch_id
            ? ApprovalSide::Origin
            : ApprovalSide::Destination;
        $recorder->record($request, 'transfer.submitted', $transfer, null, [
            'status' => $transfer->status->value,
            'revision_number' => $transfer->revision_number,
            'approval_side' => $side->value,
        ], $request->user()->current_branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Transfer diajukan dan persetujuan cabang aktif berhasil dicatat.',
        ]);
    }

    public function cancel(
        CancelBranchTransferRequest $request,
        BranchTransfer $transfer,
        BranchTransferManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $transfer);
        $before = $transfer->status->value;
        $transfer = $manager->cancel($transfer, $request->validated('reason'), $request->user());
        $recorder->record($request, 'transfer.cancelled', $transfer,
            ['status' => $before],
            ['status' => $transfer->status->value, 'reason' => $request->validated('reason')],
            $request->user()->current_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Transfer dibatalkan dan hold aset dilepas.']);
    }

    public function preflight(
        PreflightBranchTransferRequest $request,
        BranchTransferManager $manager,
        TransferEligibilityService $eligibility,
    ): JsonResponse {
        DB::beginTransaction();

        try {
            $transfer = $manager->createDraft($request->validated(), $request->user());
            $blockers = $eligibility->blockers($transfer);
            DB::rollBack();

            return response()->json([
                'eligible' => $blockers === [],
                'blockers' => $blockers,
            ]);
        } catch (\Throwable $throwable) {
            DB::rollBack();
            throw $throwable;
        }
    }

    public function options(Request $request): JsonResponse
    {
        Gate::authorize('transfers.view');
        $originId = $request->integer('from_branch_id');
        $search = trim($request->string('search')->toString());
        $companyId = $request->user()->company_id;
        Branch::query()->where('company_id', $companyId)->where('is_active', true)->findOrFail($originId);

        $products = Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%"),
            ))
            ->where(function (Builder $query) use ($originId): void {
                $query->whereHas('assets', fn (Builder $asset) => $asset
                    ->where('current_branch_id', $originId)
                    ->where('is_active', true))
                    ->orWhereHas('branchInventories', fn (Builder $inventory) => $inventory
                        ->where('branch_id', $originId)
                        ->where('quantity_on_hand', '>', 0));
            })
            ->with([
                'assets' => fn ($query) => $query
                    ->where('current_branch_id', $originId)
                    ->where('is_active', true)
                    ->orderBy('asset_code')
                    ->limit(50),
                'branchInventories' => fn ($query) => $query->where('branch_id', $originId),
            ])
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'sku', 'name', 'brand', 'model', 'tracking_type']);

        return response()->json(['products' => $products]);
    }

    private function guardAccess(Request $request, BranchTransfer $transfer): void
    {
        $accessible = $request->user()->accessibleBranches()->pluck('id');
        abort_unless(
            $accessible->contains($transfer->from_branch_id) || $accessible->contains($transfer->to_branch_id),
            404,
        );
    }

    /** @return array<string, bool> */
    private function permissions(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('transfers.create'),
            'update' => $user->can('transfers.update'),
            'approve' => $user->can('transfers.approve'),
            'cancel' => $user->can('transfers.cancel'),
            'dispatch' => $user->can('transfers.dispatch'),
            'receive' => $user->can('transfers.receive'),
            'expense' => $user->can('transfers.expense'),
            'resolve' => $user->can('transfers.resolve_discrepancy'),
            'settings' => $user->can('transfers.settings'),
            'override' => $user->can('transfers.override'),
        ];
    }
}
