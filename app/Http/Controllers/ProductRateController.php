<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Catalog\CatalogScope;
use App\Http\Requests\SaveProductRateRequest;
use App\Models\Product;
use App\Models\ProductRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProductRateController extends Controller
{
    public function store(
        SaveProductRateRequest $request,
        Product $product,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $rate = $product->rates()->create($request->validated());
        $recorder->record(
            $request,
            'catalog.product_rate.created',
            $rate,
            null,
            $rate->toArray(),
            $rate->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Harga {$product->name} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SaveProductRateRequest $request,
        ProductRate $productRate,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $oldValues = $productRate->toArray();
        $productRate->update($request->validated());
        $recorder->record(
            $request,
            'catalog.product_rate.updated',
            $productRate,
            $oldValues,
            $productRate->fresh()->toArray(),
            $productRate->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Harga produk berhasil diperbarui.',
        ]);
    }

    public function destroy(
        Request $request,
        ProductRate $productRate,
        CatalogScope $scope,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        abort_unless(
            $productRate->product()->where('company_id', $request->user()->company_id)->exists()
                && $scope->allows($request->user(), $productRate->branch_id),
            404,
        );
        $oldValues = $productRate->toArray();
        $productRate->delete();
        $recorder->record(
            $request,
            'catalog.product_rate.deleted',
            $productRate,
            $oldValues,
            null,
            $productRate->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Harga produk berhasil dihapus.',
        ]);
    }
}
