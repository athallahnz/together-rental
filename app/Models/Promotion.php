<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'company_id',
    'branch_id',
    'code',
    'name',
    'type',
    'value',
    'maximum_discount',
    'minimum_transaction',
    'bonus_duration',
    'usage_limit',
    'starts_at',
    'ends_at',
    'is_active',
    'rules',
])]
class Promotion extends Model
{
    public const TYPES = ['percentage', 'fixed', 'bonus_duration'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /** @return HasMany<Rental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    /** @return HasMany<RentalExtension, $this> */
    public function extensions(): HasMany
    {
        return $this->hasMany(RentalExtension::class);
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'maximum_discount' => 'decimal:2',
            'minimum_transaction' => 'decimal:2',
            'bonus_duration' => 'integer',
            'usage_limit' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'rules' => 'array',
        ];
    }
}
