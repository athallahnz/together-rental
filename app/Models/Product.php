<?php

namespace App\Models;

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
    'brand',
    'model',
    'tracking_type',
    'description',
    'replacement_value',
    'is_rentable',
    'is_active',
    'metadata',
])]
class Product extends Model
{
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
            'replacement_value' => 'decimal:2',
            'is_rentable' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
