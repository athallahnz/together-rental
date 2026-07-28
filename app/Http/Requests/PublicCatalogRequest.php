<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
            'category' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'brand' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/'],
            'availability' => ['nullable', Rule::in(['all', 'available'])],
            'sort' => ['nullable', Rule::in(['recommended', 'name', 'price_low', 'price_high'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->filled('search')
                ? trim((string) $this->input('search'))
                : null,
            'branch' => $this->filled('branch')
                ? trim((string) $this->input('branch'))
                : null,
            'category' => $this->filled('category')
                ? trim((string) $this->input('category'))
                : null,
            'brand' => $this->filled('brand')
                ? trim((string) $this->input('brand'))
                : null,
            'availability' => $this->input('availability', 'all'),
            'sort' => $this->input('sort', 'recommended'),
        ]);
    }
}
