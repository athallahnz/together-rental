<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'catalog_brand_id',
    'category_id',
    'name',
    'normalized_name',
    'specifications',
    'is_active',
])]
class CatalogModel extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<CatalogBrand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'catalog_brand_id');
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /** @return HasMany<CatalogModelAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(CatalogModelAlias::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
