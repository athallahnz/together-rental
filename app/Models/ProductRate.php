<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'branch_id',
    'rate_plan_id',
    'amount',
    'deposit_amount',
    'additional_hour_amount',
    'late_fee_amount',
    'valid_from',
    'valid_until',
    'is_active',
])]
class ProductRate extends Model
{
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
            'additional_hour_amount' => 'decimal:2',
            'late_fee_amount' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
