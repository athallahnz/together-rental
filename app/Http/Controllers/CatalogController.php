<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\CatalogScope;
use App\Models\CatalogBrand;
use App\Models\CatalogModel;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RatePlan;
use App\Models\RentalPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('products.view');
        $actor = $request->user();
        $search = trim($request->string('search')->toString());
        $categoryId = $request->integer('category_id') ?: null;
        $catalogBrandId = $request->integer('catalog_brand_id') ?: null;
        $catalogModelId = $request->integer('catalog_model_id') ?: null;
        $trackingType = $request->string('tracking_type')->toString();
        $status = $request->string('status')->toString();
        $section = in_array(
            $request->string('section')->toString(),
            ['products', 'categories', 'packages', 'rate-plans'],
            true,
        )
            ? $request->string('section')->toString()
            : 'products';
        $branchIds = $actor->accessibleBranches()->pluck('id');
        $productBase = Product::query()->where('company_id', $actor->company_id);

        if (
            $catalogBrandId !== null
            && ! CatalogBrand::query()
                ->where('company_id', $actor->company_id)
                ->whereKey($catalogBrandId)
                ->exists()
        ) {
            $catalogBrandId = null;
        }

        if (
            $catalogBrandId === null
            || ($catalogModelId !== null
                && ! CatalogModel::query()
                    ->where('company_id', $actor->company_id)
                    ->where('catalog_brand_id', $catalogBrandId)
                    ->whereKey($catalogModelId)
                    ->exists())
        ) {
            $catalogModelId = null;
        }

        $products = (clone $productBase)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('catalogBrand', function (Builder $brandQuery) use ($search): void {
                            $brandQuery->where(function (Builder $canonicalQuery) use ($search): void {
                                $canonicalQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('aliases', fn (Builder $aliasQuery) => $aliasQuery
                                        ->where('alias', 'like', "%{$search}%"));
                            });
                        })
                        ->orWhereHas('catalogModel', function (Builder $modelQuery) use ($search): void {
                            $modelQuery->where(function (Builder $canonicalQuery) use ($search): void {
                                $canonicalQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('aliases', fn (Builder $aliasQuery) => $aliasQuery
                                        ->where('alias', 'like', "%{$search}%"));
                            });
                        });
                });
            })
            ->when(
                $categoryId !== null,
                fn (Builder $query) => $query->where('category_id', $categoryId),
            )
            ->when(
                $catalogBrandId !== null,
                fn (Builder $query) => $query->where('catalog_brand_id', $catalogBrandId),
            )
            ->when(
                $catalogModelId !== null,
                fn (Builder $query) => $query->where('catalog_model_id', $catalogModelId),
            )
            ->when(
                in_array($trackingType, ['serialized', 'bulk'], true),
                fn (Builder $query) => $query->where('tracking_type', $trackingType),
            )
            ->when(
                in_array($status, ['active', 'inactive', 'rentable', 'not-rentable'], true),
                fn (Builder $query) => match ($status) {
                    'active' => $query->where('is_active', true),
                    'inactive' => $query->where('is_active', false),
                    'rentable' => $query->where('is_rentable', true),
                    default => $query->where('is_rentable', false),
                },
            )
            ->with([
                'category:id,code,name',
                'catalogBrand:id,name,logo_path',
                'catalogModel:id,catalog_brand_id,name',
            ])
            ->withCount(['assets', 'rates', 'packageItems'])
            ->withSum('branchInventories as quantity_on_hand', 'quantity_on_hand')
            ->withSum('branchInventories as quantity_rented', 'quantity_rented')
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        $categories = ProductCategory::query()
            ->where('company_id', $actor->company_id)
            ->with('parent:id,code,name')
            ->withCount(['children', 'products'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $ratePlans = RatePlan::query()
            ->where('company_id', $actor->company_id)
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->with('branch:id,code,name')
            ->withCount(['productRates', 'packageRates'])
            ->orderByRaw('branch_id is not null')
            ->orderBy('duration_value')
            ->get();
        $packages = RentalPackage::query()
            ->where('company_id', $actor->company_id)
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
            })
            ->with('branch:id,code,name')
            ->withCount(['items', 'rates'])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return Inertia::render('catalog/index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => CatalogBrand::query()
                ->where('company_id', $actor->company_id)
                ->where('is_active', true)
                ->whereHas('products')
                ->withCount(['products', 'models'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'logo_path', 'sort_order']),
            'models' => $catalogBrandId === null
                ? []
                : CatalogModel::query()
                    ->where('company_id', $actor->company_id)
                    ->where('catalog_brand_id', $catalogBrandId)
                    ->where('is_active', true)
                    ->whereHas('products')
                    ->withCount('products')
                    ->orderBy('name')
                    ->get(['id', 'catalog_brand_id', 'name']),
            'ratePlans' => $ratePlans,
            'packages' => $packages,
            'summary' => [
                'products' => (clone $productBase)->count(),
                'rentable' => (clone $productBase)
                    ->where('is_active', true)
                    ->where('is_rentable', true)
                    ->count(),
                'serialized' => (clone $productBase)
                    ->where('tracking_type', 'serialized')
                    ->count(),
                'packages' => $packages->count(),
                'withoutRate' => (clone $productBase)
                    ->whereDoesntHave('rates', function (Builder $query) use ($branchIds): void {
                        $query
                            ->where('is_active', true)
                            ->where(function (Builder $scopeQuery) use ($branchIds): void {
                                $scopeQuery
                                    ->whereNull('branch_id')
                                    ->orWhereIn('branch_id', $branchIds);
                            });
                    })
                    ->count(),
            ],
            'filters' => [
                'search' => $search,
                'category_id' => $categoryId,
                'catalog_brand_id' => $catalogBrandId,
                'catalog_model_id' => $catalogModelId,
                'tracking_type' => $trackingType,
                'status' => $status,
                'section' => $section,
            ],
            'branches' => $actor->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'permissions' => $this->permissions($actor),
        ]);
    }

    public function product(Request $request, Product $product): Response
    {
        Gate::authorize('products.view');
        $this->guardProduct($request, $product);
        $branchIds = $request->user()->accessibleBranches()->pluck('id');

        $product->load([
            'category:id,code,name',
            'rates' => fn ($query) => $query
                ->where(function (Builder $scopeQuery) use ($branchIds): void {
                    $scopeQuery->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                })
                ->with(['branch:id,code,name', 'ratePlan:id,branch_id,code,name,duration_unit,duration_value'])
                ->orderByDesc('is_active')
                ->orderBy('branch_id'),
            'branchInventories' => fn ($query) => $query
                ->whereIn('branch_id', $branchIds)
                ->with('branch:id,code,name')
                ->orderBy('branch_id'),
        ]);

        return Inertia::render('catalog/product-show', [
            'product' => $product,
            'assetSummary' => DB::table('assets')
                ->where('product_id', $product->id)
                ->whereNull('deleted_at')
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'categories' => ProductCategory::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'ratePlans' => RatePlan::query()
                ->where('company_id', $request->user()->company_id)
                ->where(function (Builder $query) use ($branchIds): void {
                    $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                })
                ->where('is_active', true)
                ->with('branch:id,code,name')
                ->orderBy('name')
                ->get(),
            'branches' => $request->user()->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function package(Request $request, RentalPackage $rentalPackage): Response
    {
        Gate::authorize('products.view');
        $this->guardPackage($request, $rentalPackage);
        $branchIds = $request->user()->accessibleBranches()->pluck('id');
        $rentalPackage->load([
            'branch:id,code,name',
            'items' => fn ($query) => $query
                ->with('product:id,category_id,sku,name,brand,model,is_active')
                ->orderBy('sort_order')
                ->orderBy('id'),
            'rates' => fn ($query) => $query
                ->where(function (Builder $scopeQuery) use ($branchIds): void {
                    $scopeQuery->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                })
                ->with(['branch:id,code,name', 'ratePlan:id,branch_id,code,name,duration_unit,duration_value'])
                ->orderByDesc('is_active')
                ->orderBy('branch_id'),
        ]);

        return Inertia::render('catalog/package-show', [
            'package' => $rentalPackage,
            'products' => Product::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->where('is_rentable', true)
                ->orderBy('name')
                ->get(['id', 'sku', 'name', 'brand', 'model']),
            'ratePlans' => RatePlan::query()
                ->where('company_id', $request->user()->company_id)
                ->where(function (Builder $query) use ($branchIds): void {
                    $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds);
                })
                ->where('is_active', true)
                ->with('branch:id,code,name')
                ->orderBy('name')
                ->get(),
            'branches' => $request->user()->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'permissions' => [
                ...$this->permissions($request->user()),
                'manageResource' => $request->user()->can('products.manage')
                    && app(CatalogScope::class)->allows(
                        $request->user(),
                        $rentalPackage->branch_id,
                    ),
            ],
        ]);
    }

    private function guardProduct(Request $request, Product $product): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $product->company_id === $request->user()->company_id,
            404,
        );
    }

    private function guardPackage(Request $request, RentalPackage $package): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $package->company_id === $request->user()->company_id
                && ($package->branch_id === null
                    || app(CatalogScope::class)->allows($request->user(), $package->branch_id)),
            404,
        );
    }

    /** @return array<string, bool> */
    private function permissions(User $user): array
    {
        return [
            'manage' => $user->can('products.manage'),
            'manageGlobal' => $user->can('products.manage') && $user->hasCompanyScopedRole(),
        ];
    }
}
