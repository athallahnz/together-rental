<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'rental_id',
    'original_return_id',
    'replacement_return_id',
    'correction_number',
    'status',
    'reason',
    'snapshot_before',
    'snapshot_after',
    'opened_by',
    'opened_at',
    'finalized_by',
    'finalized_at',
])]
class RentalOperationalCorrection extends Model
{
    /** @return BelongsTo<Rental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** @return BelongsTo<RentalReturn, $this> */
    public function originalReturn(): BelongsTo
    {
        return $this->belongsTo(RentalReturn::class, 'original_return_id');
    }

    /** @return BelongsTo<RentalReturn, $this> */
    public function replacementReturn(): BelongsTo
    {
        return $this->belongsTo(RentalReturn::class, 'replacement_return_id');
    }

    /** @return BelongsTo<User, $this> */
    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return BelongsTo<User, $this> */
    public function finalizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    protected function casts(): array
    {
        return [
            'snapshot_before' => 'array',
            'snapshot_after' => 'array',
            'opened_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }
}
