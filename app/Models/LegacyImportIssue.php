<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'legacy_import_row_id',
    'severity',
    'code',
    'field',
    'message',
    'original_value',
    'suggested_resolution',
    'status',
    'resolved_by',
    'resolved_at',
    'resolution_notes',
])]
class LegacyImportIssue extends Model
{
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(LegacyImportRow::class, 'legacy_import_row_id');
    }

    protected function casts(): array
    {
        return [
            'suggested_resolution' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
