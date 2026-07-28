<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function store(
        SaveProductRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();
        $product = Product::query()->create([
            ...$validated,
            'company_id' => $request->user()->company_id,
            'is_public' => $validated['is_active'] && $validated['is_rentable'],
        ]);
        $recorder->record(
            $request,
            'catalog.product.created',
            $product,
            null,
            $this->auditValues($product),
        );

        return to_route('catalog.products.show', $product)->with('toast', [
            'type' => 'success',
            'message' => "Produk {$product->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveProductRequest $request,
        Product $product,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $oldValues = $this->auditValues($product);
        $values = $request->validated();
        $identityChanged = $product->brand !== $values['brand']
            || $product->model !== $values['model'];
        $product->update([
            ...$values,
            'is_public' => $values['is_active'] && $values['is_rentable'],
            ...($identityChanged ? [
                'catalog_brand_id' => null,
                'catalog_model_id' => null,
                'variant' => null,
                'enrichment_status' => 'pending',
                'enriched_at' => null,
            ] : []),
        ]);
        $recorder->record(
            $request,
            'catalog.product.updated',
            $product,
            $oldValues,
            $this->auditValues($product->fresh()),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Produk {$product->name} berhasil diperbarui.",
        ]);
    }

    public function archive(
        Request $request,
        Product $product,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        abort_unless($product->company_id === $request->user()->company_id, 404);
        $hasActiveAssets = $product->assets()->where('is_active', true)->exists();
        $hasActivePackage = DB::table('package_items')
            ->join('packages', 'packages.id', '=', 'package_items.package_id')
            ->where('package_items.product_id', $product->id)
            ->where('packages.is_active', true)
            ->whereNull('packages.deleted_at')
            ->exists();
        $hasActiveBooking = DB::table('booking_items')
            ->join('bookings', 'bookings.id', '=', 'booking_items.booking_id')
            ->where('booking_items.product_id', $product->id)
            ->whereNotIn('bookings.status', ['completed', 'cancelled', 'converted', 'void'])
            ->whereNull('bookings.deleted_at')
            ->exists();
        $hasActiveRental = DB::table('rental_items')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->where('rental_items.product_id', $product->id)
            ->whereNotIn('rentals.status', ['returned', 'cancelled', 'void'])
            ->whereNull('rentals.deleted_at')
            ->exists();

        if ($hasActiveAssets || $hasActivePackage || $hasActiveBooking || $hasActiveRental) {
            throw ValidationException::withMessages([
                'product' => 'Produk masih terhubung ke aset, paket, booking, atau rental aktif.',
            ]);
        }

        $oldValues = $this->auditValues($product);
        $product->delete();
        $recorder->record(
            $request,
            'catalog.product.archived',
            $product,
            $oldValues,
            null,
        );

        return to_route('catalog.index')->with('toast', [
            'type' => 'success',
            'message' => "Produk {$product->name} berhasil diarsipkan.",
        ]);
    }

    /** @return array<string, mixed> */
    private function auditValues(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'brand' => $product->brand,
            'catalog_brand_id' => $product->catalog_brand_id,
            'model' => $product->model,
            'catalog_model_id' => $product->catalog_model_id,
            'variant' => $product->variant,
            'enrichment_status' => $product->enrichment_status,
            'category_id' => $product->category_id,
            'tracking_type' => $product->tracking_type,
            'replacement_value' => $product->replacement_value,
            'is_rentable' => $product->is_rentable,
            'is_active' => $product->is_active,
            'is_public' => $product->is_public,
            'is_featured' => $product->is_featured,
            'public_sort_order' => $product->public_sort_order,
        ];
    }
}
