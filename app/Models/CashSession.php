<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cash_register_id', 'opened_by', 'closed_by', 'status', 'opened_at',
    'closed_at', 'opening_balance', 'expected_closing_balance',
    'actual_closing_balance', 'difference_amount', 'opening_notes', 'closing_notes',
])]
class CashSession extends Model
{
    /** @return BelongsTo<CashRegister, $this> */
    public function register(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    /** @return HasMany<CashTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'expected_closing_balance' => 'decimal:2',
            'actual_closing_balance' => 'decimal:2',
            'difference_amount' => 'decimal:2',
        ];
    }
}
