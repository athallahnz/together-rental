<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? Gate::allows('customers.update')
                && $customer->company_id === $this->user()->company_id
            : Gate::allows('customers.create');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $target = $this->route('customer');
        $targetId = $target instanceof Customer ? $target->id : null;
        $companyId = $this->user()->company_id;

        return [
            'registered_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'institution' => ['nullable', 'string', 'max:150'],
            'is_member' => ['required', 'boolean'],
            'member_number' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('customers', 'member_number')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($targetId),
            ],
            'member_since' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked'])],
            'risk_level' => ['required', Rule::in(['low', 'normal', 'high', 'critical'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branch = Branch::query()->find($this->integer('registered_branch_id'));

                if ($branch !== null && ! $this->user()->canAccessBranch($branch)) {
                    $validator->errors()->add(
                        'registered_branch_id',
                        'Anda tidak memiliki akses ke cabang pendaftaran tersebut.',
                    );
                }

                $target = $this->route('customer');

                if (
                    $target instanceof Customer
                    && $target->risk_level !== $this->input('risk_level')
                    && ! $this->user()->can('customers.verify')
                ) {
                    $validator->errors()->add(
                        'risk_level',
                        'Perubahan risk profile membutuhkan permission verifikasi pelanggan.',
                    );
                }

                if (
                    ! ($target instanceof Customer)
                    && $this->input('risk_level') !== 'normal'
                    && ! $this->user()->can('customers.verify')
                ) {
                    $validator->errors()->add(
                        'risk_level',
                        'Risk profile khusus membutuhkan permission verifikasi pelanggan.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $isMember = $this->boolean('is_member');

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'phone' => $this->filled('phone')
                ? preg_replace('/\s+/', '', trim((string) $this->input('phone')))
                : null,
            'email' => $this->filled('email')
                ? mb_strtolower(trim((string) $this->input('email')))
                : null,
            'is_member' => $isMember,
            'member_number' => $isMember && $this->filled('member_number')
                ? mb_strtoupper(trim((string) $this->input('member_number')))
                : null,
            'member_since' => $isMember
                ? ($this->input('member_since') ?: now()->format('Y-m-d'))
                : null,
        ]);
    }
}
