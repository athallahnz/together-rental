<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductCategoryController extends Controller
{
    public function store(
        SaveProductCategoryRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $category = ProductCategory::query()->create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);
        $recorder->record(
            $request,
            'catalog.category.created',
            $category,
            null,
            $category->toArray(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kategori {$category->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveProductCategoryRequest $request,
        ProductCategory $productCategory,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $oldValues = $productCategory->toArray();
        $productCategory->update($request->validated());
        $recorder->record(
            $request,
            'catalog.category.updated',
            $productCategory,
            $oldValues,
            $productCategory->fresh()->toArray(),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kategori {$productCategory->name} berhasil diperbarui.",
        ]);
    }

    public function destroy(
        Request $request,
        ProductCategory $productCategory,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        abort_unless($productCategory->company_id === $request->user()->company_id, 404);

        if ($productCategory->children()->exists() || $productCategory->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Kategori masih memiliki subkategori atau produk.',
            ]);
        }

        $oldValues = $productCategory->toArray();
        $productCategory->delete();
        $recorder->record(
            $request,
            'catalog.category.archived',
            $productCategory,
            $oldValues,
            null,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kategori {$productCategory->name} berhasil diarsipkan.",
        ]);
    }
}
