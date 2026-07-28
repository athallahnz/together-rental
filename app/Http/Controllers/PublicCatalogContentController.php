<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\UpdateBrandPublicContentRequest;
use App\Http\Requests\UpdateCategoryPublicContentRequest;
use App\Http\Requests\UpdatePackagePublicContentRequest;
use App\Http\Requests\UpdateProductPublicContentRequest;
use App\Models\CatalogBrand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RentalPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicCatalogContentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('products.manage');

        $companyId = (int) $request->user()->company_id;

        $requestedSection = $request->string('section')->toString();

        $section = in_array(
            $requestedSection,
            ['products', 'packages', 'categories', 'brands'],
            true,
        )
            ? $requestedSection
            : 'products';

        $search = trim($request->string('search')->toString());

        $requestedStatus = $request->string('status')->toString();

        $status = in_array(
            $requestedStatus,
            ['all', 'public', 'draft', 'incomplete'],
            true,
        )
            ? $requestedStatus
            : 'all';

        $products = Product::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with([
                'category:id,name,is_public',
                'catalogBrand:id,name,is_public',
            ])
            ->withCount([
                'rates as active_rates_count' => fn (Builder $query) => $query
                    ->where('is_active', true),

                'assets as active_assets_count' => fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('status', 'available')
                    ->whereNotIn('condition', ['lost', 'retired']),
            ])
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    fn (Builder $scope) => $scope
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%"),
                ),
            )
            ->when(
                $status === 'public',
                fn (Builder $query) => $query->where('is_public', true),
            )
            ->when(
                $status === 'draft',
                fn (Builder $query) => $query->where('is_public', false),
            )
            ->orderByDesc('is_featured')
            ->orderBy('public_sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (Product $product): array => $this->mapProduct($product),
            );

        if ($status === 'incomplete') {
            $products = $products
                ->filter(
                    fn (array $product): bool => ! $product['readiness']['ready'],
                )
                ->values();
        }

        $packages = RentalPackage::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with('branch:id,code,name')
            ->withCount([
                'items',

                'rates as active_rates_count' => fn (Builder $query) => $query
                    ->where('is_active', true),
            ])
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    fn (Builder $scope) => $scope
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"),
                ),
            )
            ->orderByDesc('is_featured')
            ->orderBy('public_sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (RentalPackage $package): array => $this->mapPackage($package),
            );

        if ($status === 'public') {
            $packages = $packages
                ->where('is_public', true)
                ->values();
        } elseif ($status === 'draft') {
            $packages = $packages
                ->where('is_public', false)
                ->values();
        } elseif ($status === 'incomplete') {
            $packages = $packages
                ->filter(
                    fn (array $package): bool => ! $package['readiness']['ready'],
                )
                ->values();
        }

        $categories = ProductCategory::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->when(
                $search !== '',
                fn (Builder $query) => $query->where(
                    fn (Builder $scope) => $scope
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%"),
                ),
            )
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'code' => $category->code,
                'slug' => $category->slug,
                'description' => $category->description,
                'icon' => $category->icon,
                'image_path' => $category->image_path,
                'image_url' => $this->mediaUrl($category->image_path),
                'is_active' => $category->is_active,
                'is_public' => $category->is_public,
                'sort_order' => $category->sort_order,
                'products_count' => $category->products_count,
            ]);

        $brands = CatalogBrand::query()
            ->where('company_id', $companyId)
            ->when(
                $search !== '',
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%"),
            )
            ->withCount('products')
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (CatalogBrand $brand): array => [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
                'logo_path' => $brand->logo_path,
                'logo_url' => $brand->logo_url,
                'is_active' => $brand->is_active,
                'is_public' => $brand->is_public,
                'is_featured' => $brand->is_featured,
                'sort_order' => $brand->sort_order,
                'products_count' => $brand->products_count,
            ]);

        if ($status === 'public') {
            $categories = $categories
                ->where('is_public', true)
                ->values();

            $brands = $brands
                ->where('is_public', true)
                ->values();
        } elseif ($status === 'draft') {
            $categories = $categories
                ->where('is_public', false)
                ->values();

            $brands = $brands
                ->where('is_public', false)
                ->values();
        }

        $summary = [
            'products' => $products->count(),

            'publicProducts' => $products
                ->where('is_public', true)
                ->count(),

            'readyProducts' => $products
                ->filter(
                    fn (array $item): bool => $item['readiness']['ready'],
                )
                ->count(),

            'featuredProducts' => $products
                ->where('is_featured', true)
                ->count(),

            'publicPackages' => $packages
                ->where('is_public', true)
                ->count(),
        ];

        return Inertia::render('catalog/public-content', [
            'products' => $this->paginateArray(
                $products->all(),
                $request,
                15,
            ),

            'packages' => $this->paginateArray(
                $packages->all(),
                $request,
                15,
            ),

            'categories' => $this->paginateArray(
                $categories->all(),
                $request,
                20,
            ),

            'brands' => $this->paginateArray(
                $brands->all(),
                $request,
                20,
            ),

            'filters' => [
                'section' => $section,
                'search' => $search,
                'status' => $status,
            ],

            'summary' => $summary,
        ]);
    }

    public function updateProduct(
        UpdateProductPublicContentRequest $request,
        Product $product,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        $old = $this->productAudit($product);
        $oldPrimary = $product->primary_image_path;
        $oldGallery = $this->stringList($product->getAttribute('gallery'));

        $primary = $oldPrimary;
        $gallery = $validated['clear_gallery'] ? [] : $oldGallery;

        /** @var list<string> $newFiles */
        $newFiles = [];

        if ($validated['remove_primary_image']) {
            $primary = null;
        }

        if ($request->hasFile('primary_image')) {
            $uploadedPrimary = $request
                ->file('primary_image')
                ->store('catalog/products', 'public');

            $primary = $uploadedPrimary;
            $newFiles[] = $uploadedPrimary;
        }

        foreach ($request->file('gallery_images', []) as $image) {
            $path = $image->store(
                'catalog/products/gallery',
                'public',
            );

            $gallery[] = $path;
            $newFiles[] = $path;
        }

        $gallery = array_values(
            array_unique(
                array_slice($gallery, 0, 8),
            ),
        );

        try {
            $product->update([
                'is_public' => $validated['is_public']
                    && $product->is_active
                    && $product->is_rentable,

                'is_featured' => $validated['is_featured'],
                'public_sort_order' => $validated['public_sort_order'],
                'short_description' => $validated['short_description'],
                'seo_title' => $validated['seo_title'],
                'seo_description' => $validated['seo_description'],
                'primary_image_path' => $primary,
                'gallery' => $gallery,
            ]);
        } catch (\Throwable $exception) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            $disk->delete($newFiles);

            throw $exception;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        if ($oldPrimary !== null && $oldPrimary !== $primary) {
            $disk->delete($oldPrimary);
        }

        if ($validated['clear_gallery']) {
            $disk->delete($oldGallery);
        }

        $freshProduct = $product->fresh();

        $recorder->record(
            $request,
            'catalog.product.public-content.updated',
            $product,
            $old,
            $freshProduct instanceof Product
                ? $this->productAudit($freshProduct)
                : $this->productAudit($product),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Konten publik {$product->name} berhasil diperbarui.",
        ]);
    }

    public function updatePackage(
        UpdatePackagePublicContentRequest $request,
        RentalPackage $rentalPackage,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        $old = $rentalPackage->only([
            'is_public',
            'is_featured',
            'public_sort_order',
            'primary_image_path',
            'seo_title',
            'seo_description',
        ]);

        $oldImage = $rentalPackage->primary_image_path;

        $image = $validated['remove_primary_image']
            ? null
            : $oldImage;

        if ($request->hasFile('primary_image')) {
            $image = $request
                ->file('primary_image')
                ->store('catalog/packages', 'public');
        }

        $rentalPackage->update([
            'is_public' => $validated['is_public']
                && $rentalPackage->is_active,

            'is_featured' => $validated['is_featured'],
            'public_sort_order' => $validated['public_sort_order'],
            'seo_title' => $validated['seo_title'],
            'seo_description' => $validated['seo_description'],
            'primary_image_path' => $image,
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        if ($oldImage !== null && $oldImage !== $image) {
            $disk->delete($oldImage);
        }

        $freshPackage = $rentalPackage->fresh();

        $recorder->record(
            $request,
            'catalog.package.public-content.updated',
            $rentalPackage,
            $old,
            $freshPackage instanceof RentalPackage
                ? $freshPackage->only(array_keys($old))
                : $rentalPackage->only(array_keys($old)),
            $rentalPackage->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Konten publik {$rentalPackage->name} berhasil diperbarui.",
        ]);
    }

    public function updateCategory(
        UpdateCategoryPublicContentRequest $request,
        ProductCategory $productCategory,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        $old = $productCategory->only([
            'is_public',
            'sort_order',
            'icon',
            'image_path',
        ]);

        $oldImage = $productCategory->image_path;

        $image = $validated['remove_image']
            ? null
            : $oldImage;

        if ($request->hasFile('image')) {
            $image = $request
                ->file('image')
                ->store('catalog/categories', 'public');
        }

        $productCategory->update([
            'is_public' => $validated['is_public']
                && $productCategory->is_active,

            'sort_order' => $validated['sort_order'],
            'icon' => $validated['icon'],
            'image_path' => $image,
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        if ($oldImage !== null && $oldImage !== $image) {
            $disk->delete($oldImage);
        }

        $freshCategory = $productCategory->fresh();

        $recorder->record(
            $request,
            'catalog.category.public-content.updated',
            $productCategory,
            $old,
            $freshCategory instanceof ProductCategory
                ? $freshCategory->only(array_keys($old))
                : $productCategory->only(array_keys($old)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Konten publik {$productCategory->name} berhasil diperbarui.",
        ]);
    }

    public function updateBrand(
        UpdateBrandPublicContentRequest $request,
        CatalogBrand $catalogBrand,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        $old = $catalogBrand->only([
            'is_public',
            'is_featured',
            'sort_order',
            'logo_path',
        ]);

        $oldLogo = $catalogBrand->logo_path;

        $logo = $validated['remove_logo']
            ? null
            : $oldLogo;

        if ($request->hasFile('logo')) {
            $logo = $request
                ->file('logo')
                ->store('catalog/brands', 'public');
        }

        $catalogBrand->update([
            'is_public' => $validated['is_public']
                && $catalogBrand->is_active,

            'is_featured' => $validated['is_featured'],
            'sort_order' => $validated['sort_order'],
            'logo_path' => $logo,
        ]);

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        if ($oldLogo !== null && $oldLogo !== $logo) {
            $disk->delete($oldLogo);
        }

        $freshBrand = $catalogBrand->fresh();

        $recorder->record(
            $request,
            'catalog.brand.public-content.updated',
            $catalogBrand,
            $old,
            $freshBrand instanceof CatalogBrand
                ? $freshBrand->only(array_keys($old))
                : $catalogBrand->only(array_keys($old)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Konten publik {$catalogBrand->name} berhasil diperbarui.",
        ]);
    }

    /**
     * @template TItem
     *
     * @param  array<int, TItem>  $items
     * @return LengthAwarePaginator<int, TItem>
     */
    private function paginateArray(
        array $items,
        Request $request,
        int $perPage,
    ): LengthAwarePaginator {
        /*
         * Collection::all() diberi tipe array<int, TItem> oleh PHPStan.
         * Normalisasi indeks dilakukan di dalam method agar menjadi list
         * tanpa memaksa seluruh pemanggil menyediakan list lebih dahulu.
         */
        $normalizedItems = array_values($items);

        $page = max(
            1,
            $request->integer('page', 1),
        );

        $total = count($normalizedItems);
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $offset = ($page - 1) * $perPage;

        $pageItems = array_slice(
            $normalizedItems,
            $offset,
            $perPage,
        );

        return new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ],
        );
    }

    /** @return array<string, mixed> */
    private function mapProduct(Product $product): array
    {
        $checks = [
            'active' => $product->is_active
                && $product->is_rentable,

            'category' => $product->category !== null
                && $product->category->is_public,

            'price' => (int) $product->getAttribute(
                'active_rates_count',
            ) > 0,

            'image' => filled($product->primary_image_path),

            'description' => filled(
                $product->short_description,
            ),

            'seo' => filled($product->seo_title)
                && filled($product->seo_description),
        ];

        $score = (int) round(
            (collect($checks)->filter()->count() / count($checks)) * 100,
        );

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'is_active' => $product->is_active,
            'is_rentable' => $product->is_rentable,
            'is_public' => $product->is_public,
            'is_featured' => $product->is_featured,
            'public_sort_order' => $product->public_sort_order,
            'short_description' => $product->short_description,
            'primary_image_path' => $product->primary_image_path,
            'image_url' => $this->mediaUrl(
                $product->primary_image_path,
            ),

            'gallery' => collect(
                $this->stringList(
                    $product->getAttribute('gallery'),
                ),
            )
                ->map(
                    fn (string $path): ?string => $this->mediaUrl($path),
                )
                ->filter(
                    fn (?string $url): bool => $url !== null,
                )
                ->values()
                ->all(),

            'seo_title' => $product->seo_title,
            'seo_description' => $product->seo_description,
            'category' => $this->productCategoryName($product),
            'brand' => $this->productBrandName($product),

            'active_rates_count' => (int) $product->getAttribute(
                'active_rates_count',
            ),

            'active_assets_count' => (int) $product->getAttribute(
                'active_assets_count',
            ),

            'readiness' => [
                'score' => $score,

                'ready' => $score >= 80
                    && $checks['active']
                    && $checks['price'],

                'checks' => $checks,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function mapPackage(RentalPackage $package): array
    {
        $checks = [
            'active' => $package->is_active,

            'items' => (int) $package->items_count > 0,

            'price' => (int) $package->getAttribute(
                'active_rates_count',
            ) > 0,

            'image' => filled($package->primary_image_path),

            'seo' => filled($package->seo_title)
                && filled($package->seo_description),
        ];

        $score = (int) round(
            (collect($checks)->filter()->count() / count($checks)) * 100,
        );

        return [
            'id' => $package->id,
            'code' => $package->code,
            'name' => $package->name,
            'slug' => $package->slug,
            'description' => $package->description,
            'is_active' => $package->is_active,
            'is_public' => $package->is_public,
            'is_featured' => $package->is_featured,
            'public_sort_order' => $package->public_sort_order,
            'primary_image_path' => $package->primary_image_path,
            'image_url' => $this->mediaUrl(
                $package->primary_image_path,
            ),
            'seo_title' => $package->seo_title,
            'seo_description' => $package->seo_description,
            'branch' => $package->branch,
            'items_count' => (int) $package->items_count,

            'active_rates_count' => (int) $package->getAttribute(
                'active_rates_count',
            ),

            'readiness' => [
                'score' => $score,

                'ready' => $score >= 80
                    && $checks['items']
                    && $checks['price'],

                'checks' => $checks,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function productAudit(Product $product): array
    {
        return $product->only([
            'is_public',
            'is_featured',
            'public_sort_order',
            'short_description',
            'primary_image_path',
            'gallery',
            'seo_title',
            'seo_description',
        ]);
    }

    private function productCategoryName(Product $product): ?string
    {
        $category = $product->getRelation('category');

        return $category instanceof ProductCategory
            ? $category->name
            : null;
    }

    private function productBrandName(Product $product): ?string
    {
        $brand = $product->getRelation('catalogBrand');

        return $brand instanceof CatalogBrand
            ? $brand->name
            : $product->brand;
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    fn (mixed $item): string => is_string($item)
                        ? trim($item)
                        : '',
                    $value,
                ),
                fn (string $item): bool => $item !== '',
            ),
        );
    }

    private function mediaUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '/')
        ) {
            return $path;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }
}
