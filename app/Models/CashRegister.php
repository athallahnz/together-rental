<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['branch_id', 'code', 'name', 'is_active'])]
class CashRegister extends Model
{
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<CashSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class);
    }

    /** @return HasOne<CashSession, $this> */
    public function openSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->where('status', 'open');
    }

    /** @return HasOne<CashSession, $this> */
    public function latestSession(): HasOne
    {
        return $this->hasOne(CashSession::class)->latestOfMany();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
