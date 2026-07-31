<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id',
    'product_id',
    'quantity_on_hand',
    'quantity_reserved',
    'quantity_rented',
    'quantity_maintenance',
    'quantity_in_transfer',
    'reorder_level',
])]
class BranchInventory extends Model
{
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_rented' => 'integer',
            'quantity_maintenance' => 'integer',
            'quantity_in_transfer' => 'integer',
            'reorder_level' => 'integer',
        ];
    }
}
