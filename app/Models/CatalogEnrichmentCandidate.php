<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'run_id',
    'product_id',
    'normalized_source_name',
    'suggested_brand_id',
    'suggested_model_id',
    'suggested_brand',
    'suggested_model',
    'suggested_variant',
    'confidence',
    'source',
    'status',
    'reasoning',
    'original_brand_id',
    'original_model_id',
    'original_brand',
    'original_model',
    'original_variant',
    'original_enrichment_status',
    'original_enriched_at',
    'resolved_brand_id',
    'resolved_model_id',
    'reviewed_by',
    'reviewed_at',
    'executed_by',
    'executed_at',
])]
class CatalogEnrichmentCandidate extends Model
{
    /** @return BelongsTo<CatalogEnrichmentRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(CatalogEnrichmentRun::class, 'run_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<CatalogBrand, $this> */
    public function suggestedBrand(): BelongsTo
    {
        return $this->belongsTo(CatalogBrand::class, 'suggested_brand_id');
    }

    /** @return BelongsTo<CatalogModel, $this> */
    public function suggestedModel(): BelongsTo
    {
        return $this->belongsTo(CatalogModel::class, 'suggested_model_id');
    }

    protected function casts(): array
    {
        return [
            'run_id' => 'integer',
            'product_id' => 'integer',
            'suggested_brand_id' => 'integer',
            'suggested_model_id' => 'integer',
            'original_brand_id' => 'integer',
            'original_model_id' => 'integer',
            'resolved_brand_id' => 'integer',
            'resolved_model_id' => 'integer',
            'confidence' => 'decimal:2',
            'reasoning' => 'array',
            'original_enriched_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }
}
