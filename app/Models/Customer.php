<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'registered_branch_id',
    'customer_number',
    'name',
    'gender',
    'phone',
    'email',
    'birth_place',
    'birth_date',
    'institution',
    'is_member',
    'member_number',
    'member_since',
    'status',
    'risk_level',
    'notes',
    'created_by',
    'updated_by',
])]
class Customer extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function registeredBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'registered_branch_id');
    }

    /** @return HasMany<CustomerIdentity, $this> */
    public function identities(): HasMany
    {
        return $this->hasMany(CustomerIdentity::class);
    }

    /** @return HasOne<CustomerIdentity, $this> */
    public function primaryIdentity(): HasOne
    {
        return $this->hasOne(CustomerIdentity::class)->where('is_primary', true);
    }

    /** @return HasMany<CustomerAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /** @return HasOne<CustomerAddress, $this> */
    public function primaryAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class)->where('is_primary', true);
    }

    /** @return HasOne<LoyaltyAccount, $this> */
    public function loyaltyAccount(): HasOne
    {
        return $this->hasOne(LoyaltyAccount::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_member' => 'boolean',
            'member_since' => 'date',
        ];
    }
}
