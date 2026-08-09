<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cash.manage') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'opening_notes' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
