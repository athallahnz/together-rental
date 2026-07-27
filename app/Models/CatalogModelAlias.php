<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_model_id', 'alias', 'normalized_alias'])]
class CatalogModelAlias extends Model
{
    /** @return BelongsTo<CatalogModel, $this> */
    public function model(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class, 'catalog_model_id');
    }
}
