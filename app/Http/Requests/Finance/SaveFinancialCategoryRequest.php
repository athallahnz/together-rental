<?php

namespace App\Http\Requests\Finance;

use App\Models\FinancialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFinancialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('financialCategory');

        return $this->user()?->can('finance.categories.manage') === true
            && (! $category instanceof FinancialCategory
                || $category->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $category = $this->route('financialCategory');

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('financial_categories', 'code')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($category instanceof FinancialCategory ? $category->id : null),
            ],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['income', 'expense', 'liability'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'Kode kategori hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'code.unique' => 'Kode kategori keuangan sudah digunakan.',
            'type.in' => 'Tipe kategori keuangan tidak valid.',
        ];
    }
}
