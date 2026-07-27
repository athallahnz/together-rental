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
    'duration_unit',
    'duration_value',
    'grace_period_minutes',
    'is_active',
])]
class RatePlan extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<ProductRate, $this> */
    public function productRates(): HasMany
    {
        return $this->hasMany(ProductRate::class);
    }

    /** @return HasMany<PackageRate, $this> */
    public function packageRates(): HasMany
    {
        return $this->hasMany(PackageRate::class);
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

    protected function casts(): array
    {
        return [
            'duration_value' => 'integer',
            'grace_period_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
