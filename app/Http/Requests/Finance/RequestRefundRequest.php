<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestRefundRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->string('reason')->trim()->toString(),
            'notes' => $this->string('notes')->trim()->toString(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('refunds.request') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'payment_method_id' => [
                'required',
                'integer',
                Rule::exists('payment_methods', 'id')
                    ->where('company_id', $this->user()?->company_id)
                    ->where('is_active', true),
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
