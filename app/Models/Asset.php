<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'product_id',
    'owning_branch_id',
    'current_branch_id',
    'asset_code',
    'serial_number',
    'status',
    'condition',
    'purchase_date',
    'purchase_price',
    'replacement_value',
    'warranty_until',
    'notes',
    'is_active',
])]
class Asset extends Model
{
    use SoftDeletes;

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function owningBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'owning_branch_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    /** @return HasMany<AssetReservation, $this> */
    public function reservations(): HasMany
    {
        return $this->hasMany(AssetReservation::class);
    }

    /** @return HasMany<MaintenanceOrder, $this> */
    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }

    /** @return HasMany<BranchTransferItem, $this> */
    public function transferItems(): HasMany
    {
        return $this->hasMany(BranchTransferItem::class);
    }

    /** @return HasMany<AssetStatusHistory, $this> */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(AssetStatusHistory::class);
    }

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'purchase_price' => 'decimal:2',
            'replacement_value' => 'decimal:2',
            'warranty_until' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
