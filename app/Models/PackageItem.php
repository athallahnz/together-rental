<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'package_id',
    'product_id',
    'quantity',
    'is_optional',
    'sort_order',
])]
class PackageItem extends Model
{
    /** @return BelongsTo<RentalPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(RentalPackage::class, 'package_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_optional' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
