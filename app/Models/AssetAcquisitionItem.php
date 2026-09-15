<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_acquisition_id',
    'asset_id',
    'product_id',
    'purchase_price',
    'replacement_value',
    'serial_number',
    'warranty_until',
])]
class AssetAcquisitionItem extends Model
{
    /** @return BelongsTo<AssetAcquisition, $this> */
    public function acquisition(): BelongsTo
    {
        return $this->belongsTo(AssetAcquisition::class, 'asset_acquisition_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'replacement_value' => 'decimal:2',
            'warranty_until' => 'date',
        ];
    }
}
