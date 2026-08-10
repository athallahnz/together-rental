<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rental_extension_id', 'rental_item_id', 'quantity', 'previous_due_at',
    'extended_due_at', 'unit_rate', 'additional_amount', 'total_amount',
])]
class RentalExtensionItem extends Model
{
    /** @return BelongsTo<RentalExtension, $this> */
    public function rentalExtension(): BelongsTo
    {
        return $this->belongsTo(RentalExtension::class);
    }

    /** @return BelongsTo<RentalItem, $this> */
    public function rentalItem(): BelongsTo
    {
        return $this->belongsTo(RentalItem::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'previous_due_at' => 'datetime',
            'extended_due_at' => 'datetime',
            'unit_rate' => 'decimal:2',
            'additional_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }
}
