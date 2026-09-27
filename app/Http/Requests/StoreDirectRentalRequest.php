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
        $rules = [
            ...parent::rules(),
            ...(new CheckoutBookingRequest)->rules(),
        ];

        // Client Acceptance UAT-014: Rental In Store tidak boleh dibuat
        // tanpa minimal satu jaminan fisik/dokumen. Checkout booking biasa
        // tetap menggunakan kebijakan opsional dari CheckoutBookingRequest.
        $rules['collaterals'] = ['required', 'array', 'min:1', 'max:5'];
        $rules['customer360_received_confirmed'] = ['nullable', 'boolean'];

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'collaterals.required' => 'Minimal satu jaminan fisik/dokumen wajib diterima untuk Rental In Store.',
            'collaterals.min' => 'Minimal satu jaminan fisik/dokumen wajib diterima untuk Rental In Store.',
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $collaterals = $this->input('collaterals', []);
                if (! is_array($collaterals) || $this->boolean('customer360_received_confirmed')) {
                    return;
                }

                foreach ($collaterals as $collateral) {
                    if (is_array($collateral) && (int) ($collateral['customer_identity_id'] ?? 0) > 0) {
                        $validator->errors()->add(
                            'customer360_received_confirmed',
                            'Konfirmasi penerimaan fisik wajib dicentang sebelum memakai identitas Customer360 sebagai jaminan.',
                        );
                        break;
                    }
                }
            },
        ];
    }
}
