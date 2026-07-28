<?php

namespace App\Http\Requests;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateCategoryPublicContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('productCategory');

        return Gate::allows('products.manage')
            && $category instanceof ProductCategory
            && $category->company_id === $this->user()->company_id;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'icon' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_image' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_public' => $this->boolean('is_public'),
            'sort_order' => $this->integer('sort_order'),
            'icon' => $this->filled('icon') ? trim((string) $this->input('icon')) : null,
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
