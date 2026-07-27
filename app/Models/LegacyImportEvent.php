<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'user_id',
    'event',
    'from_status',
    'to_status',
    'context',
    'created_at',
])]
class LegacyImportEvent extends Model
{
    public const UPDATED_AT = null;

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
