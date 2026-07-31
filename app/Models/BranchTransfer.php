<?php

namespace App\Models;

use App\Domain\Transfers\Enums\TransferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $company_id
 * @property int $from_branch_id
 * @property int $to_branch_id
 * @property string $transfer_number
 * @property \App\Domain\Transfers\Enums\TransferStatus $status
 * @property int $revision_number
 * @property int $lock_version
 * @property \Illuminate\Support\Carbon|null $planned_dispatch_at
 * @property \Illuminate\Support\Carbon|null $expected_arrival_at
 * @property \Illuminate\Support\Carbon|null $requested_at
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property \Illuminate\Support\Carbon|null $shipped_at
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 */
#[Fillable([
    'company_id',
    'from_branch_id',
    'to_branch_id',
    'transfer_number',
    'status',
    'revision_number',
    'lock_version',
    'reason',
    'planned_dispatch_at',
    'expected_arrival_at',
    'shipping_notes',
    'receiving_notes',
    'shipping_method',
    'courier_name',
    'courier_phone',
    'vehicle_number',
    'tracking_number',
    'waybill_number',
    'seal_number',
    'requested_by',
    'approved_by',
    'shipped_by',
    'received_by',
    'last_material_changed_by',
    'cancelled_by',
    'requested_at',
    'approved_at',
    'shipped_at',
    'received_at',
    'last_material_changed_at',
    'cancelled_at',
    'cancellation_reason',
    'completed_at',
])]
class BranchTransfer extends Model
{
    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function originBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function shipper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<BranchTransferItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BranchTransferItem::class)->orderBy('line_number');
    }

    /** @return HasMany<BranchTransferApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(BranchTransferApproval::class);
    }

    /** @return HasMany<BranchTransferExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(BranchTransferExpense::class);
    }

    /** @return HasMany<BranchTransferDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BranchTransferDocument::class);
    }

    /** @return MorphMany<StatusHistory, $this> */
    public function statusHistories(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'subject');
    }

    public function involvesBranch(int $branchId): bool
    {
        return $this->from_branch_id === $branchId || $this->to_branch_id === $branchId;
    }

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'revision_number' => 'integer',
            'lock_version' => 'integer',
            'planned_dispatch_at' => 'datetime',
            'expected_arrival_at' => 'datetime',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'shipped_at' => 'datetime',
            'received_at' => 'datetime',
            'last_material_changed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
