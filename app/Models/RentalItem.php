<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'rental_id', 'booking_item_id', 'product_id', 'description', 'quantity',
    'returned_quantity', 'unit_rate', 'additional_amount', 'discount_amount',
    'total_amount', 'due_at', 'status',
])]
class RentalItem extends Model
{
    /** @return BelongsTo<Rental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** @return BelongsTo<BookingItem, $this> */
    public function bookingItem(): BelongsTo
    {
        return $this->belongsTo(BookingItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<RentalItemAsset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(RentalItemAsset::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_quantity' => 'integer',
            'unit_rate' => 'decimal:2',
            'additional_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'due_at' => 'datetime',
        ];
    }
}
