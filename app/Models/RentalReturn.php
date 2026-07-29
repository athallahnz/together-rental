<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_id', 'rental_id', 'received_by_employee_id', 'return_number',
    'type', 'status', 'returned_at', 'late_fee_amount', 'damage_fee_amount',
    'cleaning_fee_amount', 'discount_amount', 'total_charge_amount', 'notes',
    'created_by',
])]
class RentalReturn extends Model
{
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** @return HasMany<RentalReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RentalReturnItem::class);
    }

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'late_fee_amount' => 'decimal:2',
            'damage_fee_amount' => 'decimal:2',
            'cleaning_fee_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_charge_amount' => 'decimal:2',
        ];
    }
}
