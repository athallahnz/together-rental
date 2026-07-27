<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'company_id',
    'name',
    'normalized_name',
    'logo_path',
    'sort_order',
    'is_active',
])]
class CatalogBrand extends Model
{
    /** @var list<string> */
    protected $appends = ['logo_url'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<CatalogBrandAlias, $this> */
    public function aliases(): HasMany
    {
        return $this->hasMany(CatalogBrandAlias::class);
    }

    /** @return HasMany<CatalogModel, $this> */
    public function models(): HasMany
    {
        return $this->hasMany(CatalogModel::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return Attribute<string|null, never> */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->logo_path === null
                ? null
                : Storage::disk('public')->url($this->logo_path),
        );
    }
}
