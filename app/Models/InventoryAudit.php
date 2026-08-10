<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $branch_id
 * @property string $audit_number
 * @property string $title
 * @property string $status
 * @property int $snapshot_item_count
 * @property int $lock_version
 * @property int|null $submitted_by
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'company_id',
    'branch_id',
    'audit_number',
    'title',
    'status',
    'scheduled_at',
    'notes',
    'snapshot_item_count',
    'lock_version',
    'created_by',
    'started_by',
    'submitted_by',
    'approved_by',
    'closed_by',
    'cancelled_by',
    'started_at',
    'submitted_at',
    'approved_at',
    'closed_at',
    'cancelled_at',
    'approval_notes',
    'cancellation_reason',
])]
class InventoryAudit extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<InventoryAuditItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryAuditItem::class);
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'snapshot_item_count' => 'integer',
            'lock_version' => 'integer',
        ];
    }
}
