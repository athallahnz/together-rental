<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id', 'customer_id', 'booking_id', 'rental_id', 'payment_method_id',
    'financial_category_id', 'cash_session_id', 'payment_number', 'direction',
    'type', 'status', 'amount', 'paid_at', 'external_reference', 'proof_path',
    'notes', 'received_by',
])]
class Payment extends Model
{
    /** @return BelongsTo<PaymentMethod, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
