<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'loyalty_account_id',
    'branch_id',
    'type',
    'points',
    'balance_after',
    'source_type',
    'source_id',
    'description',
    'occurred_at',
    'expires_at',
    'created_by',
])]
class LoyaltyTransaction extends Model
{
    /** @return BelongsTo<LoyaltyAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
