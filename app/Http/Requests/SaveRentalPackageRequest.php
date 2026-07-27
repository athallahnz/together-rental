<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogScope;
use App\Models\RentalPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class SaveRentalPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('rentalPackage');

        return Gate::allows('products.manage')
            && (! $package instanceof RentalPackage
                || ($package->company_id === $this->user()->company_id
                    && app(CatalogScope::class)->allows(
                        $this->user(),
                        $package->branch_id,
                    )));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $branchId = $this->filled('branch_id') ? $this->integer('branch_id') : null;
                app(CatalogScope::class)->validate($this->user(), $branchId, $validator);
                $target = $this->route('rentalPackage');
                $duplicate = RentalPackage::query()
                    ->where('company_id', $this->user()->company_id)
                    ->where('code', $this->input('code'))
                    ->when(
                        $branchId === null,
                        fn ($query) => $query->whereNull('branch_id'),
                        fn ($query) => $query->where('branch_id', $branchId),
                    )
                    ->when(
                        $target instanceof RentalPackage,
                        fn ($query) => $query->whereKeyNot($target->id),
                    )
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('code', 'Kode paket sudah digunakan pada scope tersebut.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->integer('branch_id') : null,
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,
            'valid_from' => $this->filled('valid_from') ? $this->input('valid_from') : null,
            'valid_until' => $this->filled('valid_until') ? $this->input('valid_until') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
