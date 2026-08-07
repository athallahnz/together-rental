<?php

namespace App\Domain\PublicCatalog;

use App\Models\Branch;
use App\Models\CatalogBrand;
use App\Models\CatalogModel;
use App\Models\PackageItem;
use App\Models\PackageRate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\RentalPackage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicCatalogService
{
    /**
     * @return array<string, mixed>
     */
    public function home(?string $branchCode = null): array
    {
        $context = $this->context($branchCode);

        if ($context['branch'] === null) {
            return [
                ...$context,
                'categories' => [],
                'brands' => [],
                'featuredProducts' => [],
                'featuredPackages' => [],
                'stats' => [
                    'products' => 0,
                    'availableUnits' => 0,
                    'branches' => 0,
                ],
            ];
        }

        $branchId = (int) $context['branch']['id'];
        $companyId = (int) $context['branch']['company_id'];

        $categories = ProductCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->whereHas('products', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_rentable', true)
                ->where('is_public', true)
                ->whereNull('deleted_at'))
            ->withCount([
                'products as public_products_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('is_rentable', true)
                    ->where('is_public', true)
                    ->whereNull('deleted_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (ProductCategory $category): array => $this->mapCategory($category))
            ->values()
            ->all();

        $brands = CatalogBrand::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereHas('products', fn (Builder $query) => $query
                ->where('is_active', true)
                ->where('is_rentable', true)
                ->where('is_public', true)
                ->whereNull('deleted_at'))
            ->withCount([
                'products as public_products_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('is_rentable', true)
                    ->where('is_public', true)
                    ->whereNull('deleted_at'),
            ])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (CatalogBrand $brand): array => $this->mapBrand($brand))
            ->values()
            ->all();

        $featuredProducts = $this->baseProductQuery($companyId, $branchId)
            ->orderByDesc('is_featured')
            ->orderBy('public_sort_order')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Product $product): array => $this->mapProduct($product, $context['branch']))
            ->values()
            ->all();

        $featuredPackages = $this->basePackageQuery($companyId, $branchId)
            ->orderByDesc('is_featured')
            ->orderBy('public_sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (RentalPackage $package): array => $this->mapPackage($package, $context['branch']))
            ->values()
            ->all();

        return [
            ...$context,
            'categories' => $categories,
            'brands' => $brands,
            'featuredProducts' => $featuredProducts,
            'featuredPackages' => $featuredPackages,
            'stats' => [
                'products' => $this->visibleProducts($companyId)->count(),
                'availableUnits' => $this->availableUnitCount($branchId),
                'branches' => count($context['branches']),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function catalog(array $filters): array
    {
        $context = $this->context($this->stringOrNull($filters['branch'] ?? null));

        if ($context['branch'] === null) {
            return [
                ...$context,
                'products' => $this->emptyPaginator(),
                'categories' => [],
                'brands' => [],
                'packages' => [],
                'filters' => $filters,
            ];
        }

        $branchId = (int) $context['branch']['id'];
        $companyId = (int) $context['branch']['company_id'];
        $query = $this->baseProductQuery($companyId, $branchId);
        $search = trim((string) ($filters['search'] ?? ''));
        $category = trim((string) ($filters['category'] ?? ''));
        $brand = trim((string) ($filters['brand'] ?? ''));
        $availability = (string) ($filters['availability'] ?? 'all');
        $sort = (string) ($filters['sort'] ?? 'recommended');

        $query
            ->when($search !== '', function (Builder $productQuery) use ($search): void {
                $productQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('catalogBrand', fn (Builder $brandQuery) => $brandQuery
                            ->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('catalogModel', fn (Builder $modelQuery) => $modelQuery
                            ->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($category !== '', fn (Builder $productQuery) => $productQuery
                ->whereHas('category', fn (Builder $categoryQuery) => $categoryQuery
                    ->where('slug', $category)
                    ->where('is_public', true)))
            ->when($brand !== '', fn (Builder $productQuery) => $productQuery
                ->whereHas('catalogBrand', fn (Builder $brandQuery) => $brandQuery
                    ->where('slug', $brand)
                    ->where('is_public', true)))
            ->when($availability === 'available', fn (Builder $productQuery) => $this
                ->whereCurrentlyAvailable($productQuery, $branchId));

        if (in_array($sort, ['price_low', 'price_high'], true)) {
            $query->addSelect([
                'public_starting_price' => DB::table('product_rates')
                    ->join('rate_plans', 'rate_plans.id', '=', 'product_rates.rate_plan_id')
                    ->selectRaw('MIN(product_rates.amount)')
                    ->whereColumn('product_rates.product_id', 'products.id')
                    ->where('product_rates.is_active', true)
                    ->where('rate_plans.is_active', true)
                    ->where(function ($rateQuery) use ($branchId): void {
                        $rateQuery
                            ->whereNull('product_rates.branch_id')
                            ->orWhere('product_rates.branch_id', $branchId);
                    })
                    ->where(function ($dateQuery): void {
                        $dateQuery
                            ->whereNull('product_rates.valid_from')
                            ->orWhereDate('product_rates.valid_from', '<=', today());
                    })
                    ->where(function ($dateQuery): void {
                        $dateQuery
                            ->whereNull('product_rates.valid_until')
                            ->orWhereDate('product_rates.valid_until', '>=', today());
                    }),
            ])->orderByRaw(
                'public_starting_price IS NULL, public_starting_price '.
                    ($sort === 'price_low' ? 'ASC' : 'DESC'),
            );
        } elseif ($sort === 'name') {
            $query->orderBy('name');
        } else {
            $query
                ->orderByDesc('is_featured')
                ->orderBy('public_sort_order')
                ->orderBy('name');
        }

        $products = $query
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Product $product): array => $this->mapProduct($product, $context['branch']));

        $categories = ProductCategory::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('deleted_at')
            ->whereHas('products', fn (Builder $categoryProducts) => $categoryProducts
                ->where('is_active', true)
                ->where('is_rentable', true)
                ->where('is_public', true)
                ->whereNull('deleted_at'))
            ->withCount([
                'products as public_products_count' => fn (Builder $categoryProducts) => $categoryProducts
                    ->where('is_active', true)
                    ->where('is_rentable', true)
                    ->where('is_public', true)
                    ->whereNull('deleted_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $item): array => $this->mapCategory($item))
            ->values()
            ->all();

        $brands = CatalogBrand::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereHas('products', fn (Builder $brandProducts) => $brandProducts
                ->where('is_active', true)
                ->where('is_rentable', true)
                ->where('is_public', true)
                ->whereNull('deleted_at'))
            ->withCount([
                'products as public_products_count' => fn (Builder $brandProducts) => $brandProducts
                    ->where('is_active', true)
                    ->where('is_rentable', true)
                    ->where('is_public', true)
                    ->whereNull('deleted_at'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CatalogBrand $item): array => $this->mapBrand($item))
            ->values()
            ->all();

        $packages = $this->basePackageQuery($companyId, $branchId)
            ->orderByDesc('is_featured')
            ->orderBy('public_sort_order')
            ->orderBy('name')
            ->limit(6)
            ->get()
            ->map(fn (RentalPackage $package): array => $this->mapPackage($package, $context['branch']))
            ->values()
            ->all();

        return [
            ...$context,
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'packages' => $packages,
            'filters' => [
                'search' => $search,
                'branch' => $context['branch']['code'],
                'category' => $category,
                'brand' => $brand,
                'availability' => $availability,
                'sort' => $sort,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function product(string $slug, ?string $branchCode = null): array
    {
        $context = $this->context($branchCode);
        abort_if($context['branch'] === null, 404);

        $branchId = (int) $context['branch']['id'];
        $companyId = (int) $context['branch']['company_id'];
        $product = $this->baseProductQuery($companyId, $branchId)
            ->where('slug', $slug)
            ->firstOrFail();
        $mapped = $this->mapProduct($product, $context['branch'], true);

        $related = $this->baseProductQuery($companyId, $branchId)
            ->whereKeyNot($product->id)
            ->when(
                $product->category_id !== null,
                fn (Builder $query) => $query->where('category_id', $product->category_id),
            )
            ->orderByDesc('is_featured')
            ->orderBy('public_sort_order')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(fn (Product $item): array => $this->mapProduct($item, $context['branch']))
            ->values()
            ->all();

        return [
            ...$context,
            'product' => $mapped,
            'relatedProducts' => $related,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function package(string $slug, ?string $branchCode = null): array
    {
        $context = $this->context($branchCode);
        abort_if($context['branch'] === null, 404);

        $branchId = (int) $context['branch']['id'];
        $companyId = (int) $context['branch']['company_id'];
        $package = $this->basePackageQuery($companyId, $branchId)
            ->where('slug', $slug)
            ->firstOrFail();

        return [
            ...$context,
            'package' => $this->mapPackage($package, $context['branch'], true),
        ];
    }

    /**
     * @return array{branch: array<string, mixed>|null, branches: list<array<string, mixed>>}
     */
    private function context(?string $branchCode): array
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();

        if ($branches->isEmpty()) {
            return ['branch' => null, 'branches' => []];
        }

        /** @var list<int> $branchIds */
        $branchIds = $branches->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $settings = $this->publicSettings($branchIds);

        /** @var Collection<int, array<string, mixed>> $profiles */
        $profiles = $branches
            ->map(fn (Branch $branch): array => $this->mapBranch($branch, $settings[$branch->id] ?? []))
            ->filter(fn (array $profile): bool => (bool) $profile['catalog_enabled'])
            ->values();

        if ($profiles->isEmpty()) {
            return ['branch' => null, 'branches' => []];
        }

        $selected = $profiles->first(
            fn (array $profile): bool => $branchCode !== null
                && mb_strtolower((string) $profile['code']) === mb_strtolower($branchCode),
        ) ?? $profiles->first();

        $companyId = (int) $selected['company_id'];
        $companyProfiles = array_values(
            $profiles
                ->filter(fn (array $profile): bool => (int) $profile['company_id'] === $companyId)
                ->values()
                ->all(),
        );

        return [
            'branch' => $selected,
            'branches' => $companyProfiles,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array<int, array<string, mixed>>
     */
    private function publicSettings(array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        return DB::table('branch_settings')
            ->whereIn('branch_id', $branchIds)
            ->where('is_public', true)
            ->get(['branch_id', 'key', 'value'])
            ->groupBy('branch_id')
            ->map(function (Collection $rows): array {
                return $rows->mapWithKeys(fn (object $row): array => [
                    (string) $row->key => $this->decodeSetting($row->value),
                ])->all();
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function mapBranch(Branch $branch, array $settings): array
    {
        $whatsapp = (string) preg_replace(
            '/\D+/',
            '',
            (string) ($settings['public_whatsapp'] ?? $branch->phone ?? ''),
        );
        $instagram = ltrim((string) ($settings['public_instagram'] ?? ''), '@');
        $address = (string) ($settings['public_short_address'] ?? $branch->address ?? '');
        $logoPath = $this->stringOrNull($settings['public_logo_path'] ?? null) ?? '/primary-logos.png';

        return [
            'id' => $branch->id,
            'company_id' => $branch->company_id,
            'code' => $branch->code,
            'name' => $branch->name,
            'city' => $branch->city,
            'address' => $address,
            'opening_hours' => (string) ($settings['public_opening_hours'] ?? '09.00–21.00 WIB'),
            'whatsapp' => $whatsapp,
            'whatsapp_url' => $this->whatsappUrl(
                $whatsapp,
                "Halo {$branch->name}, saya ingin bertanya tentang rental alat.",
            ),
            'maps_url' => $this->stringOrNull($settings['public_maps_url'] ?? null),
            'instagram' => $instagram,
            'instagram_url' => $instagram !== '' ? "https://instagram.com/{$instagram}" : null,
            'logo_url' => (string) $this->mediaUrl($logoPath),
            'hero_title' => (string) ($settings['public_hero_title'] ?? 'Sewa alat kreatif tanpa ribet.'),
            'hero_description' => (string) ($settings['public_hero_description'] ?? ''),
            'catalog_enabled' => $this->settingBoolean($settings['public_catalog_enabled'] ?? false),
        ];
    }

    /** @return Builder<Product> */
    private function baseProductQuery(int $companyId, int $branchId): Builder
    {
        return $this->visibleProducts($companyId)
            ->with([
                'category:id,name,slug,image_path,is_public,is_active',
                'catalogBrand:id,name,slug,logo_path,is_public,is_active',
                'catalogModel:id,name,specifications,is_active',
                'rates' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where(function (Builder $scope) use ($branchId): void {
                        $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->where(function (Builder $dates): void {
                        $dates->whereNull('valid_from')->orWhereDate('valid_from', '<=', today());
                    })
                    ->where(function (Builder $dates): void {
                        $dates->whereNull('valid_until')->orWhereDate('valid_until', '>=', today());
                    })
                    ->with(['ratePlan:id,name,duration_unit,duration_value,is_active'])
                    ->whereHas('ratePlan', fn (Builder $plan) => $plan->where('is_active', true)),
                'branchInventories' => fn ($query) => $query->where('branch_id', $branchId),
            ])
            ->withCount([
                'assets as available_assets_count' => fn (Builder $assets) => $assets
                    ->where('current_branch_id', $branchId)
                    ->where('is_active', true)
                    ->where('status', 'available')
                    ->whereNotIn('condition', ['lost', 'retired']),
                'assets as active_assets_count' => fn (Builder $assets) => $assets
                    ->where('current_branch_id', $branchId)
                    ->where('is_active', true),
                'assets as in_transit_assets_count' => fn (Builder $assets) => $assets
                    ->where('current_branch_id', $branchId)
                    ->where('is_active', true)
                    ->where('status', 'in_transit'),
            ]);
    }

    /** @return Builder<Product> */
    private function visibleProducts(int $companyId): Builder
    {
        return Product::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_rentable', true)
            ->where('is_public', true)
            ->whereNotNull('slug')
            ->whereNull('deleted_at')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('category_id')
                    ->orWhereHas('category', fn (Builder $category) => $category
                        ->where('is_active', true)
                        ->where('is_public', true)
                        ->whereNull('deleted_at'));
            });
    }

    /** @return Builder<RentalPackage> */
    private function basePackageQuery(int $companyId, int $branchId): Builder
    {
        return RentalPackage::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNotNull('slug')
            ->whereNull('deleted_at')
            ->where(function (Builder $scope) use ($branchId): void {
                $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
            })
            ->where(function (Builder $dates): void {
                $dates->whereNull('valid_from')->orWhereDate('valid_from', '<=', today());
            })
            ->where(function (Builder $dates): void {
                $dates->whereNull('valid_until')->orWhereDate('valid_until', '>=', today());
            })
            ->with([
                'branch:id,code,name',
                'rates' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where(function (Builder $scope) use ($branchId): void {
                        $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
                    })
                    ->with(['ratePlan:id,name,duration_unit,duration_value,is_active'])
                    ->whereHas('ratePlan', fn (Builder $plan) => $plan->where('is_active', true)),
                'items' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->with([
                        'product' => fn ($productQuery) => $productQuery
                            ->where('is_active', true)
                            ->where('is_rentable', true)
                            ->where('is_public', true)
                            ->whereNull('deleted_at')
                            ->with([
                                'category:id,name,slug,image_path',
                                'catalogBrand:id,name,slug,logo_path',
                                'branchInventories' => fn ($inventory) => $inventory
                                    ->where('branch_id', $branchId),
                            ])
                            ->withCount([
                                'assets as available_assets_count' => fn (Builder $assets) => $assets
                                    ->where('current_branch_id', $branchId)
                                    ->where('is_active', true)
                                    ->where('status', 'available')
                                    ->whereNotIn('condition', ['lost', 'retired']),
                                'assets as active_assets_count' => fn (Builder $assets) => $assets
                                    ->where('current_branch_id', $branchId)
                                    ->where('is_active', true),
                                'assets as in_transit_assets_count' => fn (Builder $assets) => $assets
                                    ->where('current_branch_id', $branchId)
                                    ->where('is_active', true)
                                    ->where('status', 'in_transit'),
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private function whereCurrentlyAvailable(Builder $query, int $branchId): Builder
    {
        return $query->where(function (Builder $availability) use ($branchId): void {
            $availability
                ->where(function (Builder $serialized) use ($branchId): void {
                    $serialized
                        ->where('tracking_type', 'serialized')
                        ->whereHas('assets', fn (Builder $assets) => $assets
                            ->where('current_branch_id', $branchId)
                            ->where('is_active', true)
                            ->where('status', 'available')
                            ->whereNotIn('condition', ['lost', 'retired']));
                })
                ->orWhere(function (Builder $quantity) use ($branchId): void {
                    $quantity
                        ->where('tracking_type', '!=', 'serialized')
                        ->whereHas('branchInventories', fn (Builder $inventory) => $inventory
                            ->where('branch_id', $branchId)
                            ->whereRaw(
                                'COALESCE(branch_inventories.quantity_on_hand, 0) > '.
                                    '(COALESCE(branch_inventories.quantity_reserved, 0) + '.
                                    'COALESCE(branch_inventories.quantity_rented, 0) + '.
                                    'COALESCE(branch_inventories.quantity_maintenance, 0) + '.
                                    'COALESCE(branch_inventories.quantity_in_transfer, 0))',
                            ));
                });
        });
    }

    /**
     * @param  array<string, mixed>  $branch
     * @return array<string, mixed>
     */
    private function mapProduct(Product $product, array $branch, bool $detail = false): array
    {
        $rates = $this->mapRates($product->rates, (int) $branch['id']);
        $availability = $this->productAvailability($product);
        $category = $product->getRelation('category');
        $catalogBrand = $product->getRelation('catalogBrand');
        $catalogModel = $product->getRelation('catalogModel');
        $categoryImage = $category instanceof ProductCategory ? $category->image_path : null;
        $brandLogo = $catalogBrand instanceof CatalogBrand ? $catalogBrand->logo_url : null;
        $image = $this->mediaUrl($product->primary_image_path)
            ?? $this->mediaUrl($categoryImage)
            ?? $brandLogo;
        $description = $product->short_description
            ?? Str::limit((string) $product->description, 180);
        $message = "Halo {$branch['name']}, saya tertarik menyewa {$product->name}. Apakah tersedia?";

        $payload = [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $catalogBrand instanceof CatalogBrand ? $catalogBrand->name : $product->brand,
            'model' => $catalogModel instanceof CatalogModel ? $catalogModel->name : $product->model,
            'variant' => $product->variant,
            'tracking_type' => $product->tracking_type,
            'short_description' => $description !== '' ? $description : null,
            'image_url' => $image,
            'category' => $category instanceof ProductCategory ? [
                'name' => $category->name,
                'slug' => $category->slug,
            ] : null,
            'rates' => $rates,
            'starting_price' => $this->startingPrice($rates),
            'availability' => $availability,
            'is_featured' => $product->is_featured,
            'inquiry_url' => $this->whatsappUrl((string) $branch['whatsapp'], $message),
        ];

        if (! $detail) {
            return $payload;
        }

        return [
            ...$payload,
            'description' => $product->description,
            'seo_title' => $product->seo_title ?? $product->name,
            'seo_description' => $product->seo_description ?? $description,
            'gallery' => collect($this->stringList($product->getAttribute('gallery')))
                ->map(fn (string $path): ?string => $this->mediaUrl($path))
                ->filter()
                ->values()
                ->all(),
            'specifications' => $this->specifications(
                $catalogModel instanceof CatalogModel ? $catalogModel->specifications : null,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $branch
     * @return array<string, mixed>
     */
    private function mapPackage(RentalPackage $package, array $branch, bool $detail = false): array
    {
        $rates = $this->mapRates($package->rates, (int) $branch['id']);
        $items = $package->items
            ->map(function (PackageItem $item): ?array {
                $product = $item->product;

                if (! $product instanceof Product) {
                    return null;
                }

                $category = $product->getRelation('category');
                $catalogBrand = $product->getRelation('catalogBrand');

                return [
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'quantity' => $item->quantity,
                    'is_optional' => $item->is_optional,
                    'image_url' => $this->mediaUrl($product->primary_image_path)
                        ?? $this->mediaUrl(
                            $category instanceof ProductCategory ? $category->image_path : null,
                        )
                        ?? ($catalogBrand instanceof CatalogBrand ? $catalogBrand->logo_url : null),
                    'availability' => $this->productAvailability($product),
                ];
            })
            ->filter(fn (?array $item): bool => $item !== null)
            ->values();
        $required = $items->filter(fn (array $item): bool => ! $item['is_optional']);
        $availableUnits = $required->isEmpty()
            ? 0
            : (int) $required->map(fn (array $item): int => intdiv(
                (int) $item['availability']['available_units'],
                max((int) $item['quantity'], 1),
            ))->min();
        $message = "Halo {$branch['name']}, saya tertarik dengan paket {$package->name}. Apakah tersedia?";

        $hasInTransitItem = $required->contains(
            fn (array $item): bool => $item['availability']['status'] === 'in_transit',
        );
        $packageStatus = $availableUnits > 0
            ? 'available'
            : ($hasInTransitItem ? 'in_transit' : 'unavailable');

        $payload = [
            'id' => $package->id,
            'slug' => $package->slug,
            'name' => $package->name,
            'short_description' => Str::limit((string) $package->description, 180),
            'image_url' => $this->mediaUrl($package->primary_image_path)
                ?? $items->first()['image_url'] ?? null,
            'rates' => $rates,
            'starting_price' => $this->startingPrice($rates),
            'items_count' => $items->count(),
            'availability' => [
                'available_units' => $availableUnits,
                'status' => $packageStatus,
                'label' => match ($packageStatus) {
                    'available' => 'Tersedia',
                    'in_transit' => 'In Transit',
                    default => 'Tanyakan ketersediaan',
                },
            ],
            'is_featured' => $package->is_featured,
            'inquiry_url' => $this->whatsappUrl((string) $branch['whatsapp'], $message),
        ];

        if (! $detail) {
            return $payload;
        }

        return [
            ...$payload,
            'description' => $package->description,
            'seo_title' => $package->seo_title ?? $package->name,
            'seo_description' => $package->seo_description
                ?? Str::limit((string) $package->description, 240),
            'items' => $items->all(),
        ];
    }

    /**
     * @param  iterable<int, ProductRate|PackageRate>  $rates
     * @return list<array<string, mixed>>
     */
    private function mapRates(iterable $rates, int $branchId): array
    {
        /** @var array<int, ProductRate|PackageRate> $selectedByPlan */
        $selectedByPlan = [];

        foreach ($rates as $rate) {
            $planId = (int) $rate->rate_plan_id;
            $current = $selectedByPlan[$planId] ?? null;
            $isBranchSpecific = (int) $rate->branch_id === $branchId;
            $currentIsBranchSpecific = $current !== null
                && (int) $current->branch_id === $branchId;

            if ($current === null || ($isBranchSpecific && ! $currentIsBranchSpecific)) {
                $selectedByPlan[$planId] = $rate;
            }
        }

        $mapped = [];

        foreach ($selectedByPlan as $rate) {
            $plan = $rate->ratePlan;

            if (! $plan instanceof RatePlan) {
                continue;
            }

            $mapped[] = [
                'id' => $rate->id,
                'rate_plan' => $plan->name,
                'duration_label' => $this->durationLabel(
                    (string) $plan->duration_unit,
                    (int) $plan->duration_value,
                ),
                'duration_minutes' => $this->durationMinutes(
                    (string) $plan->duration_unit,
                    (int) $plan->duration_value,
                ),
                'amount' => (float) $rate->amount,
                'deposit_amount' => (float) $rate->deposit_amount,
            ];
        }

        usort(
            $mapped,
            fn (array $left, array $right): int => (int) $left['duration_minutes'] <=> (int) $right['duration_minutes'],
        );

        return $mapped;
    }

    /**
     * @return array{available_units: int, total_units: int, in_transit_units: int, status: string, label: string}
     */
    private function productAvailability(Product $product): array
    {
        if ($product->tracking_type === 'serialized') {
            $available = (int) ($product->available_assets_count ?? 0);
            $total = (int) ($product->active_assets_count ?? 0);
            $inTransit = (int) ($product->in_transit_assets_count ?? 0);
        } else {
            $inventory = $product->branchInventories->first();
            $inTransit = $inventory === null ? 0 : (int) $inventory->quantity_in_transfer;
            $available = $inventory === null
                ? 0
                : max(
                    0,
                    (int) $inventory->quantity_on_hand
                        - (int) $inventory->quantity_reserved
                        - (int) $inventory->quantity_rented
                        - (int) $inventory->quantity_maintenance
                        - $inTransit,
                );
            $total = $inventory === null ? 0 : (int) $inventory->quantity_on_hand;
        }

        $status = $available > 0
            ? 'available'
            : ($inTransit > 0 ? 'in_transit' : 'unavailable');
        $label = match ($status) {
            'available' => 'Tersedia',
            'in_transit' => 'In Transit',
            default => 'Tanyakan ketersediaan',
        };

        return [
            'available_units' => $available,
            'total_units' => $total,
            'in_transit_units' => $inTransit,
            'status' => $status,
            'label' => $label,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rates
     */
    private function startingPrice(array $rates): ?float
    {
        if ($rates === []) {
            return null;
        }

        return min(array_map(
            fn (array $rate): float => (float) ($rate['amount'] ?? 0),
            $rates,
        ));
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                fn (mixed $item): string => is_string($item) ? trim($item) : '',
                $value,
            ),
            fn (string $item): bool => $item !== '',
        ));
    }

    /** @return array<string, string> */
    private function specifications(mixed $specifications): array
    {
        if (! is_array($specifications)) {
            return [];
        }

        return collect($specifications)
            ->filter(fn (mixed $value, mixed $key): bool => is_scalar($value) && is_string($key))
            ->mapWithKeys(fn (mixed $value, string $key): array => [
                Str::headline($key) => (string) $value,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function mapCategory(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'image_url' => $this->mediaUrl($category->image_path),
            'products_count' => (int) ($category->public_products_count ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function mapBrand(CatalogBrand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'slug' => $brand->slug,
            'logo_url' => $brand->logo_url,
            'products_count' => (int) ($brand->public_products_count ?? 0),
        ];
    }

    private function availableUnitCount(int $branchId): int
    {
        $serialized = (int) DB::table('assets')
            ->where('current_branch_id', $branchId)
            ->where('is_active', true)
            ->where('status', 'available')
            ->whereNull('deleted_at')
            ->count();
        $quantity = DB::table('branch_inventories')
            ->where('branch_id', $branchId)
            ->get([
                'quantity_on_hand',
                'quantity_reserved',
                'quantity_rented',
                'quantity_maintenance',
                'quantity_in_transfer',
            ])
            ->sum(fn (object $inventory): int => max(
                0,
                (int) $inventory->quantity_on_hand
                    - (int) $inventory->quantity_reserved
                    - (int) $inventory->quantity_rented
                    - (int) $inventory->quantity_maintenance
                    - (int) $inventory->quantity_in_transfer,
            ));

        return $serialized + (int) $quantity;
    }

    private function durationLabel(string $unit, int $value): string
    {
        $label = match ($unit) {
            'minute' => 'Menit',
            'hour' => 'Jam',
            'day' => 'Hari',
            'week' => 'Minggu',
            'month' => 'Bulan',
            default => Str::headline($unit),
        };

        return "{$value} {$label}";
    }

    private function durationMinutes(string $unit, int $value): int
    {
        return match ($unit) {
            'minute' => $value,
            'hour' => $value * 60,
            'day' => $value * 1440,
            'week' => $value * 10080,
            'month' => $value * 43200,
            default => $value,
        };
    }

    private function mediaUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    private function whatsappUrl(string $number, string $message): ?string
    {
        $normalized = (string) preg_replace('/\D+/', '', $number);

        if ($normalized === '') {
            return null;
        }

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($message);
    }

    private function decodeSetting(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function settingBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPaginator(): LengthAwarePaginator
    {
        return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 12, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }
}
