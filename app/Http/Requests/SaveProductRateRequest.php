<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogScope;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');
        $rate = $this->route('productRate');

        return Gate::allows('products.manage')
            && ($product instanceof Product
                ? $product->company_id === $this->user()->company_id
                : $rate instanceof ProductRate
                    && $rate->product()->where('company_id', $this->user()->company_id)->exists()
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
            'additional_hour_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'late_fee_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999999.99'],
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
                $ratePlan = RatePlan::query()->find($this->integer('rate_plan_id'));

                if ($ratePlan !== null && $ratePlan->branch_id !== null && $ratePlan->branch_id !== $branchId) {
                    $validator->errors()->add(
                        'rate_plan_id',
                        'Rate plan cabang hanya dapat dipakai pada cabang yang sama.',
                    );
                }

                $product = $this->route('product');
                $target = $this->route('productRate');
                $productId = $product instanceof Product
                    ? $product->id
                    : ($target instanceof ProductRate ? $target->product_id : 0);
                $validFrom = $this->filled('valid_from') ? $this->input('valid_from') : null;
                $query = ProductRate::query()
                    ->where('product_id', $productId)
                    ->where('rate_plan_id', $this->integer('rate_plan_id'))
                    ->when(
                        $branchId === null,
                        fn ($query) => $query->whereNull('branch_id'),
                        fn ($query) => $query->where('branch_id', $branchId),
                    )
                    ->when(
                        $validFrom === null,
                        fn ($query) => $query->whereNull('valid_from'),
                        fn ($query) => $query->whereDate('valid_from', $validFrom),
                    );

                if ($target instanceof ProductRate) {
                    $query->whereKeyNot($target->id);
                }

                $duplicate = $query->exists();

                if ($duplicate) {
                    $validator->errors()->add(
                        'rate_plan_id',
                        'Harga untuk produk, cabang, rate plan, dan tanggal tersebut sudah ada.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->filled('branch_id') ? $this->integer('branch_id') : null,
            'valid_from' => $this->filled('valid_from') ? $this->input('valid_from') : null,
            'valid_until' => $this->filled('valid_until') ? $this->input('valid_until') : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
