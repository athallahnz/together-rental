<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogScope;
use App\Models\PackageRate;
use App\Models\RatePlan;
use App\Models\RentalPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePackageRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $package = $this->route('rentalPackage');
        $rate = $this->route('packageRate');

        return Gate::allows('products.manage')
            && ($package instanceof RentalPackage
                ? $package->company_id === $this->user()->company_id
                    && app(CatalogScope::class)->allows(
                        $this->user(),
                        $package->branch_id,
                    )
                : $rate instanceof PackageRate
                    && $rate->package()->where('company_id', $this->user()->company_id)->exists()
                    && app(CatalogScope::class)->allows(
                        $this->user(),
                        $rate->branch_id,
                    ));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'],
            'rate_plan_id' => [
                'required',
                'integer',
                Rule::exists('rate_plans', 'id')
                    ->where('company_id', $this->user()->company_id),
            ],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'deposit_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
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
                $ratePlan = RatePlan::query()->find($this->integer('rate_plan_id'));

                if ($ratePlan !== null && $ratePlan->branch_id !== null && $ratePlan->branch_id !== $branchId) {
                    $validator->errors()->add(
                        'rate_plan_id',
                        'Rate plan cabang hanya dapat dipakai pada cabang yang sama.',
                    );
                }

                $package = $this->route('rentalPackage');
                $target = $this->route('packageRate');
                $packageId = $package instanceof RentalPackage
                    ? $package->id
                    : ($target instanceof PackageRate ? $target->package_id : 0);
                $query = PackageRate::query()
                    ->where('package_id', $packageId)
                    ->where('rate_plan_id', $this->integer('rate_plan_id'))
                    ->when(
                        $branchId === null,
                        fn ($query) => $query->whereNull('branch_id'),
                        fn ($query) => $query->where('branch_id', $branchId),
                    );

                if ($target instanceof PackageRate) {
                    $query->whereKeyNot($target->id);
                }

                $duplicate = $query->exists();

                if ($duplicate) {
                    $validator->errors()->add(
                        'rate_plan_id',
                        'Harga paket untuk cabang dan rate plan tersebut sudah ada.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->integer('branch_id') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
