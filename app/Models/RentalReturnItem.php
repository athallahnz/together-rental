<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rental_return_id', 'rental_item_id', 'asset_id', 'quantity', 'condition',
    'status', 'late_fee_amount', 'overtime_breakdown', 'damage_fee_amount', 'cleaning_fee_amount', 'notes',
])]
class RentalReturnItem extends Model
{
    /** @return BelongsTo<RentalReturn, $this> */
    public function rentalReturn(): BelongsTo
    {
        return $this->belongsTo(RentalReturn::class);
    }

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

    /** @return HasMany<DamageCharge, $this> */
    public function damageCharges(): HasMany
    {
        return $this->hasMany(DamageCharge::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'late_fee_amount' => 'decimal:2',
            'overtime_breakdown' => 'array',
            'damage_fee_amount' => 'decimal:2',
            'cleaning_fee_amount' => 'decimal:2',
        ];
    }
}
