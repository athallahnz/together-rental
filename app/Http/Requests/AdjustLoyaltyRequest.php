<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjustLoyaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return Gate::allows('customers.loyalty')
            && $customer instanceof Customer
            && $customer->company_id === $this->user()->company_id;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['earn', 'redeem', 'adjustment'])],
            'points' => ['required', 'integer', 'not_in:0', 'between:-1000000000,1000000000'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'points' => $this->integer('points'),
            'description' => trim((string) $this->input('description')),
        ]);
    }
}
