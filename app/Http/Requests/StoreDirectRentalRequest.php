<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreDirectRentalRequest extends SaveBookingRequest
{
    public function authorize(): bool
    {
        return Gate::allows('rentals.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            ...(new CheckoutBookingRequest)->rules(),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return parent::after();
    }
}
