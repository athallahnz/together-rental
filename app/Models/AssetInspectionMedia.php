<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $captured_at
 */
#[Fillable([
    'asset_inspection_id',
    'type',
    'path',
    'caption',
    'capture_source',
    'captured_at',
    'captured_by',
    'sha256',
    'metadata',
])]
class AssetInspectionMedia extends Model
{
    /** @return BelongsTo<AssetInspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(AssetInspection::class, 'asset_inspection_id');
    }

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
