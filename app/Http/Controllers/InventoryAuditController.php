<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\InventoryAudits\InventoryAuditManager;
use App\Http\Requests\InventoryAudits\ApproveInventoryAuditRequest;
use App\Http\Requests\InventoryAudits\CancelInventoryAuditRequest;
use App\Http\Requests\InventoryAudits\RecordInventoryAuditCountRequest;
use App\Http\Requests\InventoryAudits\ResolveInventoryAuditFindingRequest;
use App\Http\Requests\InventoryAudits\ScanInventoryAuditAssetRequest;
use App\Http\Requests\InventoryAudits\StoreInventoryAuditRequest;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\InventoryAuditMedia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryAuditController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('inventory-audits.view');
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $branchIds = $user->accessibleBranches()->pluck('id')->values();
        $base = InventoryAudit::query()
            ->where('company_id', $user->company_id)
            ->whereIn('branch_id', $branchIds);

        return Inertia::render('inventory-audits/index', [
            'audits' => (clone $base)
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $nested) => $nested
                        ->where('audit_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%"),
                ))
                ->when(
                    in_array($status, ['draft', 'in_progress', 'submitted', 'approved', 'closed', 'cancelled'], true),
                    fn (Builder $query) => $query->where('status', $status),
                )
                ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->with(['branch:id,code,name', 'creator:id,name'])
                ->withCount([
                    'items',
                    'items as counted_items_count' => fn (Builder $query) => $query
                        ->where('finding_status', '!=', 'pending'),
                    'items as findings_count' => fn (Builder $query) => $query
                        ->whereIn('finding_status', ['discrepancy', 'missing', 'unexpected']),
                ])
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'summary' => [
                'active' => (clone $base)->whereIn('status', ['draft', 'in_progress', 'submitted', 'approved'])->count(),
                'submitted' => (clone $base)->where('status', 'submitted')->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
            ],
            'branches' => $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => compact('search', 'status', 'branchId'),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function show(Request $request, InventoryAudit $inventoryAudit): Response
    {
        Gate::authorize('inventory-audits.view');
        $this->guardAccess($request, $inventoryAudit);
        $search = trim($request->string('search')->toString());
        $finding = $request->string('finding')->toString();
        $trackingType = $request->string('tracking_type')->toString();
        $inventoryAudit->load([
            'branch:id,code,name',
            'creator:id,name',
            'starter:id,name',
            'submitter:id,name',
            'approver:id,name',
            'closer:id,name',
        ]);
        $itemsBase = InventoryAuditItem::query()
            ->where('inventory_audit_id', $inventoryAudit->id);

        return Inertia::render('inventory-audits/show', [
            'audit' => $inventoryAudit,
            'items' => (clone $itemsBase)
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $nested) => $nested
                        ->whereHas('asset', fn (Builder $asset) => $asset
                            ->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%"))
                        ->orWhereHas('product', fn (Builder $product) => $product
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")),
                ))
                ->when(
                    in_array($finding, ['pending', 'matched', 'verified_offsite', 'discrepancy', 'missing', 'unexpected'], true),
                    fn (Builder $query) => $query->where('finding_status', $finding),
                )
                ->when(
                    in_array($trackingType, ['serialized', 'quantity'], true),
                    fn (Builder $query) => $query->where('tracking_type', $trackingType),
                )
                ->with([
                    'product:id,sku,name,tracking_type',
                    'asset:id,product_id,current_branch_id,asset_code,serial_number,status,condition',
                    'expectedBranch:id,code,name',
                    'counter:id,name',
                    'resolver:id,name',
                    'media:id,inventory_audit_item_id,path,original_name,mime_type,capture_source,captured_at',
                ])
                ->orderByRaw("CASE finding_status WHEN 'pending' THEN 0 WHEN 'missing' THEN 1 WHEN 'unexpected' THEN 2 WHEN 'discrepancy' THEN 3 ELSE 4 END")
                ->orderBy('id')
                ->paginate(30)
                ->withQueryString(),
            'summary' => [
                'total' => (clone $itemsBase)->count(),
                'pending' => (clone $itemsBase)->where('finding_status', 'pending')->count(),
                'matched' => (clone $itemsBase)->whereIn('finding_status', ['matched', 'verified_offsite'])->count(),
                'findings' => (clone $itemsBase)->whereIn('finding_status', ['discrepancy', 'missing', 'unexpected'])->count(),
                'unresolved' => (clone $itemsBase)
                    ->whereIn('finding_status', ['discrepancy', 'missing', 'unexpected'])
                    ->whereNull('resolved_at')
                    ->count(),
            ],
            'filters' => compact('search', 'finding', 'trackingType'),
            'permissions' => $this->permissions($request),
        ]);
    }

    public function store(
        StoreInventoryAuditRequest $request,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $audit = $manager->create($request->validated(), $request->user());
        $recorder->record($request, 'inventory_audit.created', $audit, null, [
            'status' => $audit->status,
            'snapshot_item_count' => $audit->snapshot_item_count,
        ], $audit->branch_id);

        return to_route('inventory-audits.show', $audit)->with('toast', [
            'type' => 'success',
            'message' => "{$audit->audit_number} dibuat dengan snapshot {$audit->snapshot_item_count} item.",
        ]);
    }

    public function start(
        Request $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('inventory-audits.count');
        $this->guardAccess($request, $inventoryAudit);
        $before = $inventoryAudit->status;
        $audit = $manager->start($inventoryAudit, $request->user());
        $recorder->record($request, 'inventory_audit.started', $audit,
            ['status' => $before], ['status' => $audit->status], $audit->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Stock opname dimulai.']);
    }

    public function scan(
        ScanInventoryAuditAssetRequest $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $inventoryAudit);
        $code = $request->validated('code');
        $item = $manager->addScannedAsset(
            $inventoryAudit,
            is_string($code) ? $code : '',
            $request->user(),
        );
        $recorder->record($request, 'inventory_audit.asset_scanned', $inventoryAudit, null, [
            'item_id' => $item->id,
            'asset_id' => $item->asset_id,
        ], $inventoryAudit->branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Unit ditemukan dan siap dicatat pada daftar audit.',
        ]);
    }

    public function recordCount(
        RecordInventoryAuditCountRequest $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditItem $item,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardItem($request, $inventoryAudit, $item);
        $before = $item->only(['counted_quantity', 'finding_status', 'observed_status', 'observed_condition']);
        $updated = $manager->recordCount($item, $request->validated(), $request->user());
        $recorder->record($request, 'inventory_audit.item_counted', $inventoryAudit,
            $before,
            $updated->only(['id', 'counted_quantity', 'finding_status', 'observed_status', 'observed_condition']),
            $inventoryAudit->branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Hasil pemeriksaan item berhasil disimpan.',
        ]);
    }

    public function submit(
        Request $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('inventory-audits.count');
        $this->guardAccess($request, $inventoryAudit);
        $audit = $manager->submit($inventoryAudit, $request->user());
        $recorder->record($request, 'inventory_audit.submitted', $audit,
            ['status' => 'in_progress'], ['status' => $audit->status], $audit->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Hasil stock opname diajukan untuk approval.']);
    }

    public function approve(
        ApproveInventoryAuditRequest $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $inventoryAudit);
        $approvalNotes = $request->validated('approval_notes');
        $audit = $manager->approve(
            $inventoryAudit,
            is_string($approvalNotes) ? $approvalNotes : null,
            $request->user(),
        );
        $recorder->record($request, 'inventory_audit.approved', $audit,
            ['status' => 'submitted'],
            ['status' => $audit->status, 'approval_notes' => $audit->approval_notes],
            $audit->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Hasil stock opname disetujui.']);
    }

    public function resolve(
        ResolveInventoryAuditFindingRequest $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditItem $item,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardItem($request, $inventoryAudit, $item);
        $updated = $manager->resolveFinding($item, $request->validated(), $request->user());
        $recorder->record($request, 'inventory_audit.finding_resolved', $inventoryAudit, null, [
            'item_id' => $updated->id,
            'resolution_action' => $updated->resolution_action,
        ], $inventoryAudit->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Tindak lanjut temuan berhasil dicatat.']);
    }

    public function close(
        Request $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('inventory-audits.resolve');
        $this->guardAccess($request, $inventoryAudit);
        $audit = $manager->close($inventoryAudit, $request->user());
        $recorder->record($request, 'inventory_audit.closed', $audit,
            ['status' => 'approved'], ['status' => $audit->status], $audit->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Stock opname ditutup dan dikunci.']);
    }

    public function cancel(
        CancelInventoryAuditRequest $request,
        InventoryAudit $inventoryAudit,
        InventoryAuditManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $inventoryAudit);
        $before = $inventoryAudit->status;
        $reason = $request->validated('reason');
        $audit = $manager->cancel(
            $inventoryAudit,
            is_string($reason) ? $reason : '',
            $request->user(),
        );
        $recorder->record($request, 'inventory_audit.cancelled', $audit,
            ['status' => $before],
            ['status' => $audit->status, 'reason' => $audit->cancellation_reason],
            $audit->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Stock opname dibatalkan.']);
    }

    public function media(Request $request, InventoryAuditMedia $media): StreamedResponse
    {
        Gate::authorize('inventory-audits.view');
        $media->loadMissing('item.audit');
        $audit = $media->item->audit;
        $this->guardAccess($request, $audit);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return Storage::disk('local')->response(
            $media->path,
            $media->original_name ?? basename($media->path),
            ['Content-Type' => $media->mime_type ?? 'application/octet-stream'],
        );
    }

    private function guardAccess(Request $request, InventoryAudit $audit): void
    {
        abort_unless($audit->company_id === $request->user()->company_id, 404);
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($audit->branch_id)->exists(),
            404,
        );
    }

    private function guardItem(
        Request $request,
        InventoryAudit $audit,
        InventoryAuditItem $item,
    ): void {
        $this->guardAccess($request, $audit);
        abort_unless($item->inventory_audit_id === $audit->id, 404);
    }

    /** @return array<string, bool> */
    private function permissions(Request $request): array
    {
        $user = $request->user();

        return [
            'create' => $user->can('inventory-audits.create'),
            'count' => $user->can('inventory-audits.count'),
            'approve' => $user->can('inventory-audits.approve'),
            'resolve' => $user->can('inventory-audits.resolve'),
            'cancel' => $user->can('inventory-audits.cancel'),
        ];
    }
}
