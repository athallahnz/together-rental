<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'company_id',
    'code',
    'name',
    'phone',
    'email',
    'address',
    'village',
    'district',
    'city',
    'province',
    'postal_code',
    'timezone',
    'opened_at',
    'is_active',
])]
class Branch extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'primary_branch_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['is_default', 'is_active'])
            ->withTimestamps();
    }

    /** @return HasMany<Customer, $this> */
    public function registeredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'registered_branch_id');
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
