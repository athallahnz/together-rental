<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchTransferExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.expense') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'expense_branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'financial_category_id' => ['nullable', 'integer', Rule::exists('financial_categories', 'id')->where('company_id', $companyId)->where('type', 'expense')],
            'expense_type' => ['required', Rule::in(['shipping', 'packing', 'insurance', 'fuel', 'toll', 'courier', 'other'])],
            'estimated_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'actual_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'external_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
