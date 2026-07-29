<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'branch_id',
    'customer_id',
    'handled_by_employee_id',
    'rate_plan_id',
    'promotion_id',
    'booking_number',
    'legacy_number',
    'status',
    'source',
    'booked_at',
    'starts_at',
    'ends_at',
    'subtotal',
    'discount_amount',
    'tax_amount',
    'total_amount',
    'deposit_required',
    'deposit_paid',
    'notes',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
    'created_by',
    'updated_by',
])]
class Booking extends Model
{
    use SoftDeletes;

    public const ACTIVE_STATUSES = ['draft', 'confirmed'];

    public const STATUSES = ['draft', 'confirmed', 'converted', 'completed', 'cancelled', 'expired'];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<RatePlan, $this> */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    /** @return BelongsTo<Employee, $this> */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handled_by_employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /** @return HasMany<BookingItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }

    /** @return HasMany<AssetReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(AssetReservation::class);
    }

    /** @return HasMany<BookingStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'deposit_required' => 'decimal:2',
            'deposit_paid' => 'decimal:2',
        ];
    }
}
