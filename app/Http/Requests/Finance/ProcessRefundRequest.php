<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcessRefundRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'external_reference' => $this->string('external_reference')->trim()->toString(),
            'notes' => $this->string('notes')->trim()->toString(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('refunds.process') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cash_session_id' => [
                'nullable',
                'integer',
                Rule::exists('cash_sessions', 'id')->where('status', 'open'),
            ],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'proof' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }
}
