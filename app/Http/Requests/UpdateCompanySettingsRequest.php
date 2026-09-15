<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null
            && Gate::allows('company.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'tax_number' => ['nullable', 'string', 'max:40'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'timezone' => ['required', 'timezone'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'legal_name' => $this->nullableTrimmed('legal_name'),
            'tax_number' => $this->nullableTrimmed('tax_number'),
            'phone' => $this->nullableTrimmed('phone'),
            'email' => $this->nullableTrimmed('email'),
            'address' => $this->nullableTrimmed('address'),
            'timezone' => trim((string) $this->input('timezone')),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama perusahaan wajib diisi.',
            'email.email' => 'Email perusahaan harus menggunakan format email yang valid.',
            'timezone.timezone' => 'Timezone perusahaan tidak valid.',
        ];
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
