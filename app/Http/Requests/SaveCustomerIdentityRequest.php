<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCustomerIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');
        $identity = $this->route('customerIdentity');

        if (! Gate::allows('customers.update')) {
            return false;
        }

        if ($customer instanceof Customer) {
            return $customer->company_id === $this->user()->company_id;
        }

        return $identity instanceof CustomerIdentity
            && $identity->customer()->where('company_id', $this->user()->company_id)->exists();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'type' => [
                'required',
                Rule::in(['ktp', 'sim', 'passport', 'student_card', 'employee_card', 'other']),
            ],
            'number' => ['required', 'string', 'max:80'],
            'name_on_identity' => ['nullable', 'string', 'max:150'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'is_primary' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $target = $this->route('customerIdentity');
                $duplicate = CustomerIdentity::query()
                    ->where('type', $this->input('type'))
                    ->where('number', $this->input('number'))
                    ->whereHas(
                        'customer',
                        fn ($query) => $query->where('company_id', $this->user()->company_id),
                    )
                    ->when(
                        $target instanceof CustomerIdentity,
                        fn ($query) => $query->whereKeyNot($target->id),
                    )
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add(
                        'number',
                        'Nomor identitas tersebut sudah terdaftar pada pelanggan lain.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => mb_strtolower(trim((string) $this->input('type'))),
            'number' => mb_strtoupper(trim((string) $this->input('number'))),
            'name_on_identity' => $this->filled('name_on_identity')
                ? trim((string) $this->input('name_on_identity'))
                : null,
        ]);
    }
}
