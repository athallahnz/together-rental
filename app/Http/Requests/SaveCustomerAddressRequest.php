<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveCustomerAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        $address = $this->route('customerAddress');

        if (! Gate::allows('customers.update')) {
            return false;
        }

        if ($customer instanceof Customer) {
            return $customer->company_id === $this->user()->company_id;
        }

        return $address instanceof CustomerAddress
            && $address->customer()->where('company_id', $this->user()->company_id)->exists();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['identity', 'domicile', 'work', 'other'])],
            'address' => ['required', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'is_primary' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => mb_strtolower(trim((string) $this->input('type'))),
            'address' => trim((string) $this->input('address')),
        ]);
    }
}
