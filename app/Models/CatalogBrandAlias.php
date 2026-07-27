<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_brand_id', 'alias', 'normalized_alias'])]
class CatalogBrandAlias extends Model
{
    /** @return BelongsTo<CatalogBrand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'catalog_brand_id');
    }
}
