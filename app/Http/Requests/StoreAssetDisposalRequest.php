<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assets.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disposal_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(['sold', 'write_off', 'donated'])],
            'sale_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'reason' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
