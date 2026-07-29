<?php

namespace App\Http\Requests;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalReturnRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_method_id' => $this->integer('payment_method_id') ?: null,
            'payment_amount' => $this->input('payment_amount', 0) ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'returned_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method_id' => ['nullable', 'integer'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rental_item_asset_id' => ['required', 'integer', 'distinct'],
            'items.*.condition' => [
                'required',
                Rule::in(['excellent', 'good', 'fair', 'damaged', 'lost']),
            ],
            'items.*.late_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.damage_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.cleaning_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $payment = (float) $this->input('payment_amount', 0);
                $methodId = $this->input('payment_method_id');

                if ($payment > 0 && $methodId === null) {
                    $validator->errors()->add(
                        'payment_method_id',
                        'Metode pembayaran wajib dipilih jika menerima pembayaran.',
                    );

                    return;
                }

                if ($methodId === null) {
                    return;
                }

                $method = PaymentMethod::query()
                    ->whereKey($methodId)
                    ->where('company_id', $this->user()->company_id)
                    ->where('is_active', true)
                    ->first();

                if ($method === null) {
                    $validator->errors()->add(
                        'payment_method_id',
                        'Metode pembayaran tidak tersedia.',
                    );

                    return;
                }

                if ($payment > 0 && $method->requires_reference
                    && blank($this->input('payment_reference'))) {
                    $validator->errors()->add(
                        'payment_reference',
                        'Referensi pembayaran wajib diisi untuk metode ini.',
                    );
                }
            },
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('rentals.return') === true;
    }
}
