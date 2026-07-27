<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return Gate::allows('products.manage')
            && (! $product instanceof Product
                || $product->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
            ],
            'sku' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('products', 'sku')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at')
                    ->ignore($productId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'tracking_type' => ['required', Rule::in(['serialized', 'bulk'])],
            'description' => ['nullable', 'string', 'max:3000'],
            'replacement_value' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'is_rentable' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'sku' => mb_strtoupper(trim((string) $this->input('sku'))),
            'name' => trim((string) $this->input('name')),
            'brand' => $this->filled('brand') ? trim((string) $this->input('brand')) : null,
            'model' => $this->filled('model') ? trim((string) $this->input('model')) : null,
            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,
            'is_rentable' => $this->boolean('is_rentable'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
