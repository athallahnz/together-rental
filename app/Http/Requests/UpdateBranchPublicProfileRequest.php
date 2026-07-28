<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateBranchPublicProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            && $branch->company_id === $this->user()->company_id
            && Gate::allows('branches.manage');
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'public_catalog_enabled' => ['required', 'boolean'],
            'public_whatsapp' => [
                'nullable',
                'required_if:public_catalog_enabled,true',
                'string',
                'max:30',
                'regex:/^[0-9]+$/',
            ],
            'public_short_address' => [
                'nullable',
                'required_if:public_catalog_enabled,true',
                'string',
                'max:1000',
            ],
            'public_maps_url' => ['nullable', 'url:http,https', 'max:2000'],
            'public_instagram' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._]+$/',
            ],
            'public_opening_hours' => [
                'nullable',
                'required_if:public_catalog_enabled,true',
                'string',
                'max:100',
            ],
            'public_logo_path' => ['nullable', 'string', 'max:255'],
            'public_hero_title' => [
                'nullable',
                'required_if:public_catalog_enabled,true',
                'string',
                'max:160',
            ],
            'public_hero_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $whatsapp = preg_replace('/\D+/', '', (string) $this->input('public_whatsapp'));
        $instagram = ltrim(trim((string) $this->input('public_instagram')), '@');

        $this->merge([
            'public_catalog_enabled' => $this->boolean('public_catalog_enabled'),
            'public_whatsapp' => $whatsapp === '' ? null : $whatsapp,
            'public_short_address' => $this->nullableTrimmed('public_short_address'),
            'public_maps_url' => $this->nullableTrimmed('public_maps_url'),
            'public_instagram' => $instagram === '' ? null : $instagram,
            'public_opening_hours' => $this->nullableTrimmed('public_opening_hours'),
            'public_logo_path' => $this->nullableTrimmed('public_logo_path'),
            'public_hero_title' => $this->nullableTrimmed('public_hero_title'),
            'public_hero_description' => $this->nullableTrimmed('public_hero_description'),
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'public_whatsapp.required_if' => 'Nomor WhatsApp wajib diisi ketika katalog publik diaktifkan.',
            'public_whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka dan harus memakai kode negara, contoh 6285784771927.',
            'public_short_address.required_if' => 'Alamat publik wajib diisi ketika katalog publik diaktifkan.',
            'public_maps_url.url' => 'Google Maps URL harus berupa tautan HTTP atau HTTPS yang valid.',
            'public_instagram.regex' => 'Username Instagram hanya boleh berisi huruf, angka, titik, dan garis bawah.',
            'public_opening_hours.required_if' => 'Jam operasional wajib diisi ketika katalog publik diaktifkan.',
            'public_hero_title.required_if' => 'Judul hero wajib diisi ketika katalog publik diaktifkan.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $branch = $this->route('branch');

            if (
                $branch instanceof Branch
                && $this->boolean('public_catalog_enabled')
                && ! $branch->is_active
            ) {
                $validator->errors()->add(
                    'public_catalog_enabled',
                    'Cabang harus berstatus operasional sebelum ditampilkan pada katalog publik.',
                );
            }
        });
    }

    private function nullableTrimmed(string $key): ?string
    {
        $value = trim((string) $this->input($key));

        return $value === '' ? null : $value;
    }
}
