<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Maintenance\MaintenanceManager;
use App\Http\Requests\CancelMaintenanceOrderRequest;
use App\Http\Requests\CompleteMaintenanceOrderRequest;
use App\Http\Requests\StoreMaintenanceOrderRequest;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MaintenanceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('maintenance.view');
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $branchIds = $user->accessibleBranches()->select('id');
        $base = MaintenanceOrder::query()->whereIn('branch_id', $branchIds);

        return Inertia::render('maintenance/index', [
            'maintenanceOrders' => (clone $base)
                ->when($search !== '', fn (Builder $query) => $query->where(
                    fn (Builder $nested) => $nested
                        ->where('maintenance_number', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhereHas('asset', fn (Builder $asset) => $asset
                            ->where('asset_code', 'like', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $product) => $product
                                ->where('name', 'like', "%{$search}%"))),
                ))
                ->when(in_array($status, ['reported', 'in_progress', 'completed', 'cancelled'], true),
                    fn (Builder $query) => $query->where('status', $status))
                ->when($branchId !== null,
                    fn (Builder $query) => $query->where('branch_id', $branchId))
                ->with([
                    'branch:id,code,name',
                    'asset:id,product_id,asset_code,serial_number,status,condition',
                    'asset.product:id,sku,name',
                ])
                ->latest('reported_at')
                ->paginate(20)
                ->withQueryString(),
            'summary' => [
                'reported' => (clone $base)->where('status', 'reported')->count(),
                'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
                'actual_cost' => (float) (clone $base)->where('status', 'completed')
                    ->whereNotNull('completed_at')->sum('actual_cost'),
            ],
            'assets' => Asset::query()
                ->whereIn('current_branch_id', $branchIds)
                ->where('is_active', true)
                ->whereNotIn('status', ['rented', 'reserved', 'lost', 'retired'])
                ->whereDoesntHave('maintenanceOrders',
                    fn (Builder $query) => $query->whereIn('status', ['reported', 'in_progress']))
                ->with(['product:id,sku,name', 'currentBranch:id,code,name'])
                ->orderBy('asset_code')
                ->get(['id', 'product_id', 'current_branch_id', 'asset_code', 'serial_number', 'status', 'condition']),
            'branches' => $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']),
            'filters' => compact('search', 'status', 'branchId'),
            'permissions' => [
                'manage' => $user->can('maintenance.manage'),
            ],
        ]);
    }

    public function show(Request $request, MaintenanceOrder $maintenance): Response
    {
        Gate::authorize('maintenance.view');
        $this->guardAccess($request, $maintenance);
        $maintenance->load([
            'branch:id,code,name',
            'asset.product:id,sku,name',
            'creator:id,name',
            'completer:id,name',
        ]);

        $histories = $maintenance->asset->statusHistories()
            ->where('source_type', MaintenanceOrder::class)
            ->where('source_id', $maintenance->id)
            ->with('changer:id,name')
            ->latest('changed_at')
            ->get();

        return Inertia::render('maintenance/show', [
            'maintenance' => $maintenance,
            'histories' => $histories,
            'permissions' => [
                'manage' => $request->user()->can('maintenance.manage'),
            ],
        ]);
    }

    public function store(
        StoreMaintenanceOrderRequest $request,
        MaintenanceManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $maintenance = $manager->create($request->validated(), $request->user());
        $recorder->record($request, 'maintenance.reported', $maintenance, null, [
            'status' => $maintenance->status,
            'asset_id' => $maintenance->asset_id,
            'estimated_cost' => $maintenance->estimated_cost,
        ], $maintenance->branch_id);

        return to_route('maintenance.show', $maintenance)->with('toast', [
            'type' => 'success',
            'message' => "{$maintenance->maintenance_number} berhasil dibuat.",
        ]);
    }

    public function start(
        Request $request,
        MaintenanceOrder $maintenance,
        MaintenanceManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('maintenance.manage');
        $this->guardAccess($request, $maintenance);
        $before = $maintenance->status;
        $maintenance = $manager->start($maintenance, $request->user());
        $recorder->record($request, 'maintenance.started', $maintenance,
            ['status' => $before], ['status' => $maintenance->status], $maintenance->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pengerjaan maintenance dimulai.']);
    }

    public function complete(
        CompleteMaintenanceOrderRequest $request,
        MaintenanceOrder $maintenance,
        MaintenanceManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $maintenance);
        $before = $this->audit($maintenance);
        $maintenance = $manager->complete($maintenance, $request->validated(), $request->user());
        $recorder->record($request, 'maintenance.completed', $maintenance,
            $before, $this->audit($maintenance), $maintenance->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Maintenance berhasil diselesaikan.']);
    }

    public function cancel(
        CancelMaintenanceOrderRequest $request,
        MaintenanceOrder $maintenance,
        MaintenanceManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $maintenance);
        $before = $maintenance->status;
        $maintenance = $manager->cancel(
            $maintenance,
            $request->validated('reason'),
            $request->user(),
        );
        $recorder->record($request, 'maintenance.cancelled', $maintenance,
            ['status' => $before], ['status' => $maintenance->status, 'reason' => $request->validated('reason')],
            $maintenance->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Maintenance dibatalkan.']);
    }

    private function guardAccess(Request $request, MaintenanceOrder $maintenance): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($maintenance->branch_id)->exists(),
            404,
        );
    }

    /** @return array<string, mixed> */
    private function audit(MaintenanceOrder $maintenance): array
    {
        return $maintenance->only([
            'status', 'resolution', 'actual_cost', 'started_at', 'completed_at', 'completed_by',
        ]);
    }
}
