<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class QuoteDirectRentalRequest extends SaveBookingRequest
{
    public function authorize(): bool
    {
        return Gate::allows('rentals.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        // Payment and collateral fields are deliberately absent during quotation.
        return array_intersect_key(parent::rules(), array_flip([
            'branch_id', 'customer_id', 'rate_plan_id', 'source', 'starts_at',
            'duration_units', 'promotion_code', 'items', 'items.*.type',
            'items.*.id', 'items.*.quantity',
        ]));
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        $this->merge(['payment_amount' => 0, 'deposit_paid' => 0]);
    }
}
