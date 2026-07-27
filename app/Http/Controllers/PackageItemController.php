<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Catalog\CatalogScope;
use App\Http\Requests\SavePackageItemRequest;
use App\Models\PackageItem;
use App\Models\RentalPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PackageItemController extends Controller
{
    public function store(
        SavePackageItemRequest $request,
        RentalPackage $rentalPackage,
        CatalogScope $scope,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        abort_unless($scope->allows($request->user(), $rentalPackage->branch_id), 404);
        $item = $rentalPackage->items()->create($request->validated());
        $recorder->record(
            $request,
            'catalog.package_item.created',
            $item,
            null,
            $item->toArray(),
            $rentalPackage->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Produk berhasil ditambahkan ke paket.',
        ]);
    }

    public function destroy(
        Request $request,
        PackageItem $packageItem,
        CatalogScope $scope,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        $packageItem->load('package');
        abort_unless(
            $packageItem->package->company_id === $request->user()->company_id
                && $scope->allows($request->user(), $packageItem->package->branch_id),
            404,
        );
        $oldValues = $packageItem->toArray();
        $branchId = $packageItem->package->branch_id;
        $packageItem->delete();
        $recorder->record(
            $request,
            'catalog.package_item.deleted',
            $packageItem,
            $oldValues,
            null,
            $branchId,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Produk berhasil dikeluarkan dari paket.',
        ]);
    }
}
