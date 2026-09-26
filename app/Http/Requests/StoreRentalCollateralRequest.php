<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRentalCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rentals.update') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'source_mode' => ['required', Rule::in(['existing', 'new', 'manual'])],
            'customer_identity_id' => ['nullable', 'integer'],
            'identity_type' => [
                'nullable',
                Rule::in(['ktp', 'sim', 'passport', 'student_card', 'employee_card', 'other']),
            ],
            'identity_number' => ['nullable', 'string', 'max:80'],
            'identity_name_on_identity' => ['nullable', 'string', 'max:150'],
            'identity_expires_at' => ['nullable', 'date_format:Y-m-d'],
            'identity_is_primary' => ['nullable', 'boolean'],
            'save_to_customer360' => ['nullable', 'boolean'],
            'type' => ['nullable', 'string', 'max:40'],
            'number' => ['nullable', 'string', 'max:100'],
            'holder_name' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'physical_received' => ['accepted'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $mode = $this->string('source_mode')->toString();

                if ($mode === 'existing' && $this->integer('customer_identity_id') <= 0) {
                    $validator->errors()->add(
                        'customer_identity_id',
                        'Pilih identitas Customer360 yang diterima sebagai jaminan.',
                    );
                }

                if ($mode === 'new') {
                    if (! $this->filled('identity_type')) {
                        $validator->errors()->add('identity_type', 'Jenis identitas wajib dipilih.');
                    }

                    if (! $this->filled('identity_number')) {
                        $validator->errors()->add('identity_number', 'Nomor identitas wajib diisi.');
                    }

                    if (
                        $this->boolean('save_to_customer360')
                        && $this->user()?->can('customers.update') !== true
                    ) {
                        $validator->errors()->add(
                            'save_to_customer360',
                            'Akun ini tidak memiliki izin untuk menyimpan identitas ke Customer360.',
                        );
                    }
                }

                if ($mode === 'manual') {
                    if (! $this->filled('type')) {
                        $validator->errors()->add('type', 'Jenis jaminan wajib diisi.');
                    }

                    if (! $this->filled('number')) {
                        $validator->errors()->add('number', 'Nomor atau keterangan jaminan wajib diisi.');
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $identityId = (int) $this->input('customer_identity_id', 0);
        $mode = trim((string) $this->input('source_mode'));

        if ($mode === '') {
            $mode = $identityId > 0 ? 'existing' : 'manual';
        }

        $this->merge([
            'source_mode' => $mode,
            'customer_identity_id' => $identityId > 0 ? $identityId : null,
            'identity_type' => $this->filled('identity_type')
                ? mb_strtolower(trim((string) $this->input('identity_type')))
                : null,
            'identity_number' => $this->filled('identity_number')
                ? mb_strtoupper(trim((string) $this->input('identity_number')))
                : null,
            'identity_name_on_identity' => $this->filled('identity_name_on_identity')
                ? trim((string) $this->input('identity_name_on_identity'))
                : null,
            'type' => $this->filled('type')
                ? trim((string) $this->input('type'))
                : null,
            'number' => $this->filled('number')
                ? trim((string) $this->input('number'))
                : null,
            'holder_name' => $this->filled('holder_name')
                ? trim((string) $this->input('holder_name'))
                : null,
        ]);
    }
}
