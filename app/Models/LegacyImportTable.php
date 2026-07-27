<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'batch_id',
    'source_table',
    'target_table',
    'status',
    'expected_rows',
    'parsed_rows',
    'valid_rows',
    'warning_rows',
    'error_rows',
    'imported_rows',
    'summary',
])]
class LegacyImportTable extends Model
{
    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }

    protected function casts(): array
    {
        return [
            'expected_rows' => 'integer',
            'parsed_rows' => 'integer',
            'valid_rows' => 'integer',
            'warning_rows' => 'integer',
            'error_rows' => 'integer',
            'imported_rows' => 'integer',
            'summary' => 'array',
        ];
    }
}
