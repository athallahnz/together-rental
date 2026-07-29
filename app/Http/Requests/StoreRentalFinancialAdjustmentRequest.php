<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRentalFinancialAdjustmentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'component' => ['required', Rule::in(['charge', 'payment', 'deposit'])],
            'direction' => ['required', Rule::in(['increase', 'decrease'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'component' => 'komponen koreksi',
            'direction' => 'arah koreksi',
            'amount' => 'nominal koreksi',
            'reason' => 'alasan koreksi',
        ];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('rentals.correct_completed') === true;
    }
}
