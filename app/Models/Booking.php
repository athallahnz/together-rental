<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'customer_id',
    'booking_number',
    'legacy_number',
    'status',
    'source',
    'booked_at',
    'starts_at',
    'ends_at',
    'total_amount',
])]
class Booking extends Model
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
            'booked_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'total_amount' => 'decimal:2',
        ];
    }
}
