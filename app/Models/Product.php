<?php

namespace App\Models;

use App\Models\Concerns\HasPublicSlug;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'category_id',
    'sku',
    'name',
    'slug',
    'brand',
    'catalog_brand_id',
    'model',
    'catalog_model_id',
    'variant',
    'enrichment_status',
    'enriched_at',
    'tracking_type',
    'description',
    'short_description',
    'primary_image_path',
    'gallery',
    'seo_title',
    'seo_description',
    'replacement_value',
    'is_rentable',
    'is_active',
    'is_public',
    'is_featured',
    'public_sort_order',
    'metadata',
])]
class Product extends Model
{
    use HasPublicSlug;
    use SoftDeletes;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** @return BelongsTo<CatalogBrand, $this> */
    public function catalogBrand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class);
    }

    /** @return BelongsTo<CatalogModel, $this> */
    public function catalogModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class);
    }

    /** @return HasMany<ProductRate, $this> */
    public function rates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasMany<BranchInventory, $this> */
    public function branchInventories(): HasMany
    {
        return $this->hasMany(BranchInventory::class);
    }

    /** @return HasMany<PackageItem, $this> */
    public function packageItems(): HasMany
    {
        return $this->hasMany(PackageItem::class);
    }

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'category_id' => 'integer',
            'catalog_brand_id' => 'integer',
            'catalog_model_id' => 'integer',
            'replacement_value' => 'decimal:2',
            'is_rentable' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'public_sort_order' => 'integer',
            'gallery' => 'array',
            'metadata' => 'array',
            'enriched_at' => 'datetime',
        ];
    }
}
