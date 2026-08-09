<?php

namespace App\Http\Requests\Finance;

use App\Models\CashRegister;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $register = $this->route('cashRegister');

        if ($this->user()?->can('finance.cash_registers.manage') !== true) {
            return false;
        }

        return ! $register instanceof CashRegister
            || $register->branch()->where('company_id', $this->user()->company_id)->exists();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $register = $this->route('cashRegister');
        $branchId = $this->integer('branch_id');

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->where('is_active', true),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('cash_registers', 'code')
                    ->where('branch_id', $branchId)
                    ->ignore($register instanceof CashRegister ? $register->id : null),
            ],
            'name' => ['required', 'string', 'max:100'],
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
            'branch_id.exists' => 'Cabang kasir tidak tersedia atau sedang nonaktif.',
            'code.regex' => 'Kode kasir hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'code.unique' => 'Kode kasir sudah digunakan pada cabang ini.',
        ];
    }
}
