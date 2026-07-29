<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rental_item_id', 'asset_id', 'checkout_condition', 'return_condition',
    'checked_out_at', 'returned_at', 'status', 'notes',
])]
class RentalItemAsset extends Model
{
    /** @return BelongsTo<RentalItem, $this> */
    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }
}
