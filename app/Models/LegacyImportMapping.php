<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'mapping_type',
    'source_value',
    'target_table',
    'target_id',
    'transform_rule',
    'is_confirmed',
    'confirmed_by',
    'confirmed_at',
])]
class LegacyImportMapping extends Model
{
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }

    protected function casts(): array
    {
        return [
            'target_id' => 'integer',
            'transform_rule' => 'array',
            'is_confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }
}
