<?php

namespace App\Http\Requests;

use App\Models\CatalogBrand;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateBrandPublicContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $brand = $this->route('catalogBrand');

        return Gate::allows('products.manage')
            && $this->user()->hasCompanyScopedRole()
            && $brand instanceof CatalogBrand
            && $brand->company_id === $this->user()->company_id;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_public' => $this->boolean('is_public'),
            'is_featured' => $this->boolean('is_featured'),
            'sort_order' => $this->integer('sort_order'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);
    }
}
