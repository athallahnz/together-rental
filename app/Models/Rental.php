<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'booking_id',
    'customer_id',
    'checked_out_by_employee_id',
    'rate_plan_id',
    'rental_number',
    'legacy_number',
    'status',
    'checked_out_at',
    'due_at',
    'returned_at',
    'subtotal',
    'booking_payment_amount',
    'deposit_amount',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'paid_amount',
    'balance_due',
    'late_fee_amount',
    'damage_fee_amount',
    'notes',
    'created_by',
    'updated_by',
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

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<RatePlan, $this> */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /** @return HasMany<RentalItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    /** @return HasMany<RentalStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(RentalStatusHistory::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<RentalReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(RentalReturn::class);
    }

    /** @return HasMany<RentalFinancialAdjustment, $this> */
    public function financialAdjustments(): HasMany
    {
        return $this->hasMany(RentalFinancialAdjustment::class);
    }

    protected function casts(): array
    {
        return [
            'checked_out_at' => 'datetime',
            'due_at' => 'datetime',
            'returned_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'booking_payment_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'damage_fee_amount' => 'decimal:2',
        ];
    }
}
