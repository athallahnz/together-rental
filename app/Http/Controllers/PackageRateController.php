<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Catalog\CatalogScope;
use App\Http\Requests\SavePackageRateRequest;
use App\Models\PackageRate;
use App\Models\RentalPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PackageRateController extends Controller
{
    public function store(
        SavePackageRateRequest $request,
        RentalPackage $rentalPackage,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $rate = $rentalPackage->rates()->create($request->validated());
        $recorder->record(
            $request,
            'catalog.package_rate.created',
            $rate,
            null,
            $rate->toArray(),
            $rate->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Harga paket {$rentalPackage->name} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SavePackageRateRequest $request,
        PackageRate $packageRate,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $oldValues = $packageRate->toArray();
        $packageRate->update($request->validated());
        $recorder->record(
            $request,
            'catalog.package_rate.updated',
            $packageRate,
            $oldValues,
            $packageRate->fresh()->toArray(),
            $packageRate->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Harga paket berhasil diperbarui.',
        ]);
    }

    public function destroy(
        Request $request,
        PackageRate $packageRate,
        CatalogScope $scope,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        abort_unless(
            $packageRate->package()->where('company_id', $request->user()->company_id)->exists()
                && $scope->allows($request->user(), $packageRate->branch_id),
            404,
        );
        $oldValues = $packageRate->toArray();
        $packageRate->delete();
        $recorder->record(
            $request,
            'catalog.package_rate.deleted',
            $packageRate,
            $oldValues,
            null,
            $packageRate->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Harga paket berhasil dihapus.',
        ]);
    }
}
