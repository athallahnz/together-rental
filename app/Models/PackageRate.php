<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'package_id',
    'branch_id',
    'rate_plan_id',
    'amount',
    'deposit_amount',
    'is_active',
])]
class PackageRate extends Model
{
    /** @return BelongsTo<RentalPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(RentalPackage::class, 'package_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<RatePlan, $this> */
    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
