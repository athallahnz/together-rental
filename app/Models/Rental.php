<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'customer_id',
    'rental_number',
    'legacy_number',
    'status',
    'checked_out_at',
    'due_at',
    'returned_at',
    'total_amount',
    'paid_amount',
    'balance_due',
    'late_fee_amount',
    'damage_fee_amount',
])]
class Rental extends Model
{
    use SoftDeletes;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'damage_fee_amount' => 'decimal:2',
        ];
    }
}
