<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'booking_id', 'product_id', 'package_id', 'description', 'quantity',
    'unit_rate', 'additional_amount', 'discount_amount', 'total_amount',
])]
class BookingItem extends Model
{
    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<RentalPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(RentalPackage::class, 'package_id');
    }

    /** @return HasMany<AssetReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(AssetReservation::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_rate' => 'decimal:2',
            'additional_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
