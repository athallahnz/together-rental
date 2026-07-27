<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'status',
    'scope',
    'ai_requested',
    'ai_provider',
    'generated_count',
    'approved_count',
    'executed_count',
    'verified_count',
    'rollback_skipped_count',
    'options',
    'generated_by',
    'executed_by',
    'executed_at',
    'rolled_back_by',
    'rolled_back_at',
])]
class CatalogEnrichmentRun extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<CatalogEnrichmentCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(CatalogEnrichmentCandidate::class, 'run_id');
    }

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'ai_requested' => 'boolean',
            'generated_count' => 'integer',
            'approved_count' => 'integer',
            'executed_count' => 'integer',
            'verified_count' => 'integer',
            'rollback_skipped_count' => 'integer',
            'options' => 'array',
            'executed_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }
}
