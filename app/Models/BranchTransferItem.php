<?php

namespace App\Models;

use App\Domain\Transfers\Enums\ReceivingResult;
use App\Domain\Transfers\Enums\TransferItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_transfer_id
 * @property int $product_id
 * @property int|null $asset_id
 * @property int $quantity
 * @property int $received_quantity
 * @property \App\Domain\Transfers\Enums\TransferItemStatus $status
 * @property \App\Domain\Transfers\Enums\ReceivingResult|null $receiving_result
 * @property \Illuminate\Support\Carbon|null $resolved_at
 */
#[Fillable([
    'branch_transfer_id',
    'line_number',
    'product_id',
    'asset_id',
    'previous_branch_id',
    'previous_asset_status',
    'quantity',
    'received_quantity',
    'condition_before',
    'condition_after',
    'receiving_result',
    'discrepancy_type',
    'discrepancy_notes',
    'resolution_action',
    'resolved_by',
    'resolved_at',
    'status',
    'notes',
])]
class BranchTransferItem extends Model
{
    /** @return BelongsTo<BranchTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function previousBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'previous_branch_id');
    }

    /** @return HasMany<AssetInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(AssetInspection::class);
    }

    /** @return HasMany<BranchTransferDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BranchTransferDocument::class);
    }

    public function isSerialized(): bool
    {
        return $this->asset_id !== null;
    }

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'integer',
            'received_quantity' => 'integer',
            'status' => TransferItemStatus::class,
            'receiving_result' => ReceivingResult::class,
            'resolved_at' => 'datetime',
        ];
    }
}
