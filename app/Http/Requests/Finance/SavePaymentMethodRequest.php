<?php

namespace App\Http\Requests\Finance;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route('paymentMethod');

        return $this->user()?->can('finance.payment_methods.manage') === true
            && (! $method instanceof PaymentMethod
                || $method->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $method = $this->route('paymentMethod');

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('payment_methods', 'code')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($method instanceof PaymentMethod ? $method->id : null),
            ],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in([
                'cash',
                'bank_transfer',
                'qris',
                'card',
                'other',
            ])],
            'requires_reference' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'requires_reference' => $this->boolean('requires_reference'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode metode hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'code.unique' => 'Kode metode pembayaran sudah digunakan.',
            'type.in' => 'Tipe metode pembayaran tidak valid.',
        ];
    }
}
