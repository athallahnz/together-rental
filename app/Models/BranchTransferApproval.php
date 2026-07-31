<?php

namespace App\Models;

use App\Domain\Transfers\Enums\ApprovalDecision;
use App\Domain\Transfers\Enums\ApprovalSide;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \App\Domain\Transfers\Enums\ApprovalSide $side
 * @property \App\Domain\Transfers\Enums\ApprovalDecision $decision
 * @property array<string, mixed>|null $payload_snapshot
 * @property \Illuminate\Support\Carbon|null $decided_at
 */
#[Fillable([
    'branch_transfer_id',
    'revision_number',
    'side',
    'branch_id',
    'decision',
    'decided_by',
    'decided_at',
    'notes',
    'payload_snapshot',
    'snapshot_hash',
])]
class BranchTransferApproval extends Model
{
    /** @return BelongsTo<BranchTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'side' => ApprovalSide::class,
            'decision' => ApprovalDecision::class,
            'decided_at' => 'datetime',
            'payload_snapshot' => 'array',
        ];
    }
}
