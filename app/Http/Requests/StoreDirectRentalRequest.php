<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
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
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $hasPayment = $this->float('payment_amount') > 0
                    || $this->float('deposit_paid') > 0;

                if ($hasPayment && ! $this->filled('payment_method_id')) {
                    $validator->errors()->add(
                        'payment_method_id',
                        'Metode pembayaran wajib dipilih.',
                    );
                }

                if (! $this->filled('payment_method_id')) {
                    return;
                }

                $method = PaymentMethod::query()
                    ->where('company_id', $this->user()->company_id)
                    ->where('is_active', true)
                    ->find($this->integer('payment_method_id'));

                if ($method === null) {
                    $validator->errors()->add(
                        'payment_method_id',
                        'Metode pembayaran tidak tersedia.',
                    );
                } elseif (
                    $method->requires_reference
                    && ! $this->filled('payment_reference')
                ) {
                    $validator->errors()->add(
                        'payment_reference',
                        'Referensi pembayaran wajib diisi.',
                    );
                }
            },
        ];
    }
}
