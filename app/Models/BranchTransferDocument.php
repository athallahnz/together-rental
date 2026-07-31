<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $captured_at
 */
#[Fillable([
    'branch_transfer_id',
    'branch_transfer_item_id',
    'branch_transfer_expense_id',
    'stage',
    'document_type',
    'path',
    'original_name',
    'mime_type',
    'file_size',
    'sha256',
    'capture_source',
    'captured_at',
    'uploaded_by',
    'metadata',
])]
class BranchTransferDocument extends Model
{
    /** @return BelongsTo<BranchTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    /** @return BelongsTo<BranchTransferItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(BranchTransferItem::class, 'branch_transfer_item_id');
    }

    /** @return BelongsTo<BranchTransferExpense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(BranchTransferExpense::class, 'branch_transfer_expense_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
