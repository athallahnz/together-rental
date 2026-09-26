<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Assets\AssetLifecycleManager;
use App\Http\Requests\StoreAssetAcquisitionRequest;
use App\Http\Requests\StoreAssetDisposalRequest;
use App\Models\Asset;
use App\Models\AssetAcquisition;
use App\Models\AssetDisposal;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssetLifecycleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('assets.view');
        $actor = $request->user();
        $branches = $actor->accessibleBranches()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $branchIds = $branches->pluck('id');
        $branchId = $request->integer('branch_id') ?: null;
        $search = trim($request->string('search')->toString());

        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }

        $scopeBranchIds = $branchId === null ? $branchIds : collect([$branchId]);
        $acquisitionScope = AssetAcquisition::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('branch_id', $scopeBranchIds);
        $disposalScope = AssetDisposal::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('branch_id', $scopeBranchIds);

        return Inertia::render('assets/lifecycle', [
            'summary' => [
                'active_assets' => Asset::query()
                    ->whereIn('current_branch_id', $scopeBranchIds)
                    ->where('is_active', true)
                    ->whereHas('product', fn (Builder $query) => $query
                        ->where('company_id', $actor->company_id)
                        ->where('tracking_type', 'serialized'))
                    ->count(),
                'acquisition_count' => (clone $acquisitionScope)->count(),
                'acquisition_value' => (float) (clone $acquisitionScope)->sum('total_amount'),
                'disposal_count' => (clone $disposalScope)->count(),
                'sale_proceeds' => (float) (clone $disposalScope)->sum('sale_amount'),
            ],
            'acquisitions' => (clone $acquisitionScope)
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('acquisition_number', 'like', "%{$search}%")
                            ->orWhere('vendor_name', 'like', "%{$search}%")
                            ->orWhere('reference_number', 'like', "%{$search}%")
                            ->orWhereHas('items.asset', fn (Builder $assetQuery) => $assetQuery
                                ->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%"))
                            ->orWhereHas('items.product', fn (Builder $productQuery) => $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%"));
                    });
                })
                ->with([
                    'branch:id,code,name',
                    'creator:id,name',
                    'items.asset:id,asset_code,serial_number,status,is_active',
                    'items.product:id,sku,name',
                ])
                ->withCount('items')
                ->orderByDesc('acquisition_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'disposals' => (clone $disposalScope)
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('disposal_number', 'like', "%{$search}%")
                            ->orWhere('reason', 'like', "%{$search}%")
                            ->orWhereHas('asset', fn (Builder $assetQuery) => $assetQuery
                                ->where('asset_code', 'like', "%{$search}%")
                                ->orWhere('serial_number', 'like', "%{$search}%")
                                ->orWhereHas('product', fn (Builder $productQuery) => $productQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")));
                    });
                })
                ->with([
                    'branch:id,code,name',
                    'disposer:id,name',
                    'asset:id,product_id,asset_code,serial_number,status,condition,purchase_price',
                    'asset.product:id,sku,name',
                ])
                ->orderByDesc('disposal_date')
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
            'disposableAssets' => Asset::query()
                ->whereIn('current_branch_id', $scopeBranchIds)
                ->where('is_active', true)
                ->whereIn('status', ['available', 'lost'])
                ->whereHas('product', fn (Builder $query) => $query
                    ->where('company_id', $actor->company_id)
                    ->where('tracking_type', 'serialized'))
                ->when($search !== '', function (Builder $query) use ($search): void {
                    $query->where(function (Builder $nested) use ($search): void {
                        $nested->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $productQuery) => $productQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%"));
                    });
                })
                ->with(['product:id,sku,name', 'currentBranch:id,code,name'])
                ->orderBy('asset_code')
                ->limit(100)
                ->get([
                    'id', 'product_id', 'current_branch_id', 'asset_code', 'serial_number',
                    'status', 'condition', 'purchase_date', 'purchase_price', 'is_active',
                ]),
            'products' => Product::query()
                ->where('company_id', $actor->company_id)
                ->where('tracking_type', 'serialized')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'replacement_value']),
            'branches' => $branches,
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
            ],
            'permissions' => [
                'manage' => $actor->can('assets.manage'),
                'inspect' => $actor->can('assets.inspect'),
            ],
            'defaultBranchId' => $actor->current_branch_id
                ?? ($branches->isEmpty() ? null : $branches->first()->id),
        ]);
    }

    public function storeAcquisition(
        StoreAssetAcquisitionRequest $request,
        AssetLifecycleManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $acquisition = $manager->acquire($request->validated(), $request->user());
        $recorder->record(
            $request,
            'asset.acquired',
            $acquisition,
            null,
            [
                'acquisition_number' => $acquisition->acquisition_number,
                'acquisition_date' => (string) $acquisition->getRawOriginal('acquisition_date'),
                'branch_id' => $acquisition->branch_id,
                'total_amount' => (float) $acquisition->total_amount,
                'asset_count' => $acquisition->items()->count(),
            ],
            $acquisition->branch_id,
        );

        return redirect()->route('assets.lifecycle.index', ['branch_id' => $acquisition->branch_id])
            ->with('toast', [
                'type' => 'success',
                'message' => __('uat035b_stage3.toast.assetlifecycle_1', ['number' => $acquisition->acquisition_number]),
            ]);
    }

    public function dispose(
        StoreAssetDisposalRequest $request,
        Asset $asset,
        AssetLifecycleManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = [
            'status' => $asset->status,
            'is_active' => (bool) $asset->is_active,
            'current_branch_id' => $asset->current_branch_id,
        ];
        $disposal = $manager->dispose($asset, $request->validated(), $request->user());
        $asset->refresh();
        $recorder->record(
            $request,
            'asset.disposed',
            $disposal,
            $before,
            [
                'disposal_number' => $disposal->disposal_number,
                'method' => $disposal->method,
                'sale_amount' => (float) $disposal->sale_amount,
                'status' => $asset->status,
                'is_active' => (bool) $asset->is_active,
            ],
            $disposal->branch_id,
        );

        return redirect()->route('assets.lifecycle.index', ['branch_id' => $disposal->branch_id])
            ->with('toast', [
                'type' => 'success',
                'message' => __('uat035b_stage3.toast.assetlifecycle_2', ['number' => $disposal->disposal_number]),
            ]);
    }
}
