<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id',
    'asset_id',
    'rental_item_id',
    'rental_return_item_id',
    'branch_transfer_item_id',
    'type',
    'condition',
    'checklist',
    'notes',
    'inspected_by',
    'inspected_at',
])]
class AssetInspection extends Model
{
    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<BranchTransferItem, $this> */
    public function transferItem(): BelongsTo
    {
        return $this->belongsTo(BranchTransferItem::class, 'branch_transfer_item_id');
    }

    /** @return HasMany<AssetInspectionMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(AssetInspectionMedia::class);
    }

    protected function casts(): array
    {
        return [
            'checklist' => 'array',
            'inspected_at' => 'datetime',
        ];
    }
}
