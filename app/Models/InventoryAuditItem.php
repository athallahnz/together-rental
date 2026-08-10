<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_audit_id
 * @property int $product_id
 * @property int|null $asset_id
 * @property int|null $expected_branch_id
 * @property string $tracking_type
 * @property string|null $expected_status
 * @property string|null $expected_condition
 * @property int $expected_quantity
 * @property int|null $counted_quantity
 * @property string $finding_status
 * @property list<string>|null $issue_flags
 * @property string|null $observed_status
 * @property string|null $observed_condition
 * @property string|null $resolution_action
 * @property Carbon|null $counted_at
 * @property Carbon|null $resolved_at
 */
#[Fillable([
    'inventory_audit_id',
    'product_id',
    'asset_id',
    'expected_branch_id',
    'tracking_type',
    'expected_status',
    'expected_condition',
    'expected_quantity',
    'counted_quantity',
    'finding_status',
    'issue_flags',
    'observed_status',
    'observed_condition',
    'notes',
    'resolution_action',
    'resolution_notes',
    'counted_by',
    'resolved_by',
    'counted_at',
    'resolved_at',
])]
class InventoryAuditItem extends Model
{
    /** @return BelongsTo<InventoryAudit, $this> */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(InventoryAudit::class, 'inventory_audit_id');
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
    public function expectedBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'expected_branch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<InventoryAuditMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(InventoryAuditMedia::class);
    }

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'counted_quantity' => 'integer',
            'issue_flags' => 'array',
            'counted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
