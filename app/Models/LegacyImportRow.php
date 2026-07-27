<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'batch_id',
    'source_table',
    'legacy_key',
    'row_number',
    'payload',
    'normalized_payload',
    'fingerprint',
    'status',
    'issue_count',
    'target_table',
    'target_id',
    'imported_at',
])]
class LegacyImportRow extends Model
{
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(LegacyImportIssue::class);
    }

    protected function casts(): array
    {
        return [
            'row_number' => 'integer',
            'payload' => 'array',
            'normalized_payload' => 'array',
            'issue_count' => 'integer',
            'target_id' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
