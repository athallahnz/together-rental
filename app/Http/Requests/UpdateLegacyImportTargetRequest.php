<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLegacyImportTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('imports.validate');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'import_prefix' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'confirm_target' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'branch_id.required' => 'Pilih kota/cabang tujuan import.',
            'branch_id.exists' => 'Kota/cabang tujuan import tidak tersedia.',
            'import_prefix.required' => 'PREFIX import wajib diisi.',
            'import_prefix.size' => 'PREFIX import wajib tepat 3 huruf.',
            'import_prefix.regex' => 'PREFIX import hanya boleh terdiri dari 3 huruf A-Z.',
            'confirm_target.accepted' => 'Konfirmasi kota/cabang dan PREFIX sebelum menyimpan tujuan import.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'branch_id' => 'kota/cabang tujuan',
            'import_prefix' => 'PREFIX import',
            'confirm_target' => 'konfirmasi tujuan import',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('import_prefix')) {
            $this->merge([
                'import_prefix' => strtoupper(trim((string) $this->input('import_prefix'))),
            ]);
        }
    }
}
