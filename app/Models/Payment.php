<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'branch_id', 'customer_id', 'booking_id', 'rental_id', 'rental_extension_id',
    'payment_method_id',
    'financial_category_id', 'cash_session_id', 'payment_number', 'direction',
    'type', 'source_context', 'status', 'amount', 'paid_at', 'external_reference',
    'proof_path', 'notes', 'received_by', 'voided_by', 'voided_at', 'void_reason',
])]
class Payment extends Model
{
    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Rental, $this> */
    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    /** @return BelongsTo<RentalExtension, $this> */
    public function rentalExtension(): BelongsTo
    {
        return $this->belongsTo(RentalExtension::class);
    }

    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /** @return BelongsTo<FinancialCategory, $this> */
    public function financialCategory(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class);
    }

    /** @return BelongsTo<CashSession, $this> */
    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @return HasMany<CashTransaction, $this> */
    public function cashTransactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** @return HasOne<BranchTransferExpense, $this> */
    public function transferExpense(): HasOne
    {
        return $this->hasOne(BranchTransferExpense::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
