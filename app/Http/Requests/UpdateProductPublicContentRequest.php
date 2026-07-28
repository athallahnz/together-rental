<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateProductPublicContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return Gate::allows('products.manage')
            && $product instanceof Product
            && $product->company_id === $this->user()->company_id;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'public_sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'short_description' => ['nullable', 'string', 'max:320'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'primary_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'gallery_images' => ['nullable', 'array', 'max:8'],
            'gallery_images.*' => ['image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_primary_image' => ['required', 'boolean'],
            'clear_gallery' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_public' => $this->boolean('is_public'),
            'is_featured' => $this->boolean('is_featured'),
            'public_sort_order' => $this->integer('public_sort_order'),
            'short_description' => $this->filled('short_description')
                ? trim((string) $this->input('short_description'))
                : null,
            'seo_title' => $this->filled('seo_title')
                ? trim((string) $this->input('seo_title'))
                : null,
            'seo_description' => $this->filled('seo_description')
                ? trim((string) $this->input('seo_description'))
                : null,
            'remove_primary_image' => $this->boolean('remove_primary_image'),
            'clear_gallery' => $this->boolean('clear_gallery'),
        ]);
    }
}
