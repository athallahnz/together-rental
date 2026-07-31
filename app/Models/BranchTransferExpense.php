<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'branch_transfer_id',
    'expense_branch_id',
    'financial_category_id',
    'payment_method_id',
    'cash_session_id',
    'payment_id',
    'expense_type',
    'status',
    'estimated_amount',
    'actual_amount',
    'vendor_name',
    'paid_at',
    'external_reference',
    'notes',
    'created_by',
    'paid_by',
    'voided_by',
    'voided_at',
    'void_reason',
])]
class BranchTransferExpense extends Model
{
    /** @return BelongsTo<BranchTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(BranchTransfer::class, 'branch_transfer_id');
    }

    /** @return BelongsTo<Branch, $this> */
    public function expenseBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'expense_branch_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return HasMany<BranchTransferDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(BranchTransferDocument::class);
    }

    protected function casts(): array
    {
        return [
            'estimated_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
