<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogScope;
use App\Models\RentalPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePackagePublicContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('rentalPackage');

        return Gate::allows('products.manage')
            && $package instanceof RentalPackage
            && $package->company_id === $this->user()->company_id
            && app(CatalogScope::class)->allows($this->user(), $package->branch_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'is_public' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'public_sort_order' => ['required', 'integer', 'min:0', 'max:999999'],
            'seo_title' => ['nullable', 'string', 'max:180'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'primary_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
            'remove_primary_image' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_public' => $this->boolean('is_public'),
            'is_featured' => $this->boolean('is_featured'),
            'public_sort_order' => $this->integer('public_sort_order'),
            'seo_title' => $this->filled('seo_title') ? trim((string) $this->input('seo_title')) : null,
            'seo_description' => $this->filled('seo_description')
                ? trim((string) $this->input('seo_description'))
                : null,
            'remove_primary_image' => $this->boolean('remove_primary_image'),
        ]);
    }
}
