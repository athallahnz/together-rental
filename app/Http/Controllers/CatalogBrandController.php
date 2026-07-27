<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Models\CatalogBrand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CatalogBrandController extends Controller
{
    public function updateVisual(
        Request $request,
        CatalogBrand $catalogBrand,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->authorizeBrandManager($request, $catalogBrand);
        $validated = $request->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
        $oldLogoPath = $catalogBrand->logo_path;
        $oldSortOrder = $catalogBrand->sort_order;
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->store('catalog/brands', 'public');
        }

        try {
            $catalogBrand->update([
                'logo_path' => $newLogoPath ?? $oldLogoPath,
                'sort_order' => (int) $validated['sort_order'],
            ]);
        } catch (\Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath !== null && $oldLogoPath !== null && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        $recorder->record(
            $request,
            'catalog.brand.visual.updated',
            $catalogBrand,
            [
                'logo_path' => $oldLogoPath,
                'sort_order' => $oldSortOrder,
            ],
            [
                'logo_path' => $catalogBrand->logo_path,
                'sort_order' => $catalogBrand->sort_order,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Visual brand {$catalogBrand->name} berhasil diperbarui.",
        ]);
    }

    public function destroyLogo(
        Request $request,
        CatalogBrand $catalogBrand,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->authorizeBrandManager($request, $catalogBrand);
        $oldLogoPath = $catalogBrand->logo_path;

        $catalogBrand->update(['logo_path' => null]);

        if ($oldLogoPath !== null) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        $recorder->record(
            $request,
            'catalog.brand.logo.removed',
            $catalogBrand,
            ['logo_path' => $oldLogoPath],
            ['logo_path' => null],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Logo brand {$catalogBrand->name} berhasil dihapus.",
        ]);
    }

    private function authorizeBrandManager(Request $request, CatalogBrand $catalogBrand): void
    {
        Gate::authorize('products.manage');
        abort_unless(
            $request->user()->company_id !== null
                && $request->user()->hasCompanyScopedRole()
                && $catalogBrand->company_id === $request->user()->company_id,
            403,
        );
    }
}
