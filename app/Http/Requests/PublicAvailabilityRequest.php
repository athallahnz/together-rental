<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'type' => ['required', Rule::in(['product', 'package'])],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\\TH:i', 'after:starts_at'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'rate_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        if (app()->getLocale() === 'en') {
            return [
                'branch.required' => 'Select a branch first.',
                'starts_at.required' => 'Enter the rental start date and time.',
                'ends_at.required' => 'Enter the rental end date and time.',
                'ends_at.after' => 'The end time must be after the start time.',
                'quantity.max' => 'Public availability checks are limited to 20 units per request.',
            ];
        }

        return [
            'branch.required' => 'Pilih cabang terlebih dahulu.',
            'starts_at.required' => 'Isi tanggal dan jam mulai rental.',
            'ends_at.required' => 'Isi tanggal dan jam selesai rental.',
            'ends_at.after' => 'Waktu selesai harus setelah waktu mulai.',
            'quantity.max' => 'Pengecekan publik dibatasi maksimal 20 unit per permintaan.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch' => mb_strtoupper(trim((string) $this->input('branch'))),
            'type' => mb_strtolower(trim((string) $this->input('type'))),
            'slug' => mb_strtolower(trim((string) $this->input('slug'))),
            'quantity' => $this->input('quantity', 1),
            'rate_id' => $this->filled('rate_id') ? $this->input('rate_id') : null,
        ]);
    }
}
