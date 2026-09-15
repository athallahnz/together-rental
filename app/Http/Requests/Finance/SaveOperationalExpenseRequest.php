<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOperationalExpenseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'vendor_name' => $this->string('vendor_name')->trim()->toString(),
            'external_reference' => $this->string('external_reference')->trim()->toString(),
            'notes' => $this->string('notes')->trim()->toString(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('expenses.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'financial_category_id' => ['required', 'integer', Rule::exists('financial_categories', 'id')->where('company_id', $companyId)->where('type', 'expense')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'incurred_at' => ['required', 'date'],
            'vendor_name' => ['nullable', 'string', 'max:160'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
