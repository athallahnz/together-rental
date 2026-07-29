<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rental_return_item_id', 'asset_id', 'type', 'description', 'amount',
    'status', 'decided_by', 'decided_at', 'decision_notes',
])]
class DamageCharge extends Model
{
    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(RentalReturnItem::class, 'rental_return_item_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }
}
