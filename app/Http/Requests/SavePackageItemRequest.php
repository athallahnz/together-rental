<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogScope;
use App\Models\RentalPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePackageItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('rentalPackage');

        return Gate::allows('products.manage')
            && $package instanceof RentalPackage
            && $package->company_id === $this->user()->company_id
            && app(CatalogScope::class)->allows(
                $this->user(),
                $package->branch_id,
            );
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        /** @var RentalPackage $package */
        $package = $this->route('rentalPackage');

        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
                Rule::unique('package_items', 'product_id')
                    ->where('package_id', $package->id),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'is_optional' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_optional' => $this->boolean('is_optional'),
            'sort_order' => $this->integer('sort_order'),
        ]);
    }
}
