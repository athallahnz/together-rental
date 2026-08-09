<?php

namespace App\Http\Requests;

use App\Domain\Catalog\CatalogScope;
use App\Models\RatePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ratePlan = $this->route('ratePlan');

        return Gate::allows('products.manage')
            && (! $ratePlan instanceof RatePlan
                || ($ratePlan->company_id === $this->user()->company_id
                    && app(CatalogScope::class)->allows(
                        $this->user(),
                        $ratePlan->branch_id,
                    )));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9-]+$/'],
            'name' => ['required', 'string', 'max:100'],
            'duration_unit' => ['required', Rule::in(['minute', 'hour', 'day', 'week', 'month'])],
            'duration_value' => ['required', 'integer', 'min:1', 'max:10000'],
            'grace_period_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
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
                $target = $this->route('ratePlan');
                $query = RatePlan::query()
                    ->where('company_id', $this->user()->company_id)
                    ->where('code', $this->input('code'))
                    ->when(
                        $branchId === null,
                        fn ($query) => $query->whereNull('branch_id'),
                        fn ($query) => $query->where('branch_id', $branchId),
                    );

                if ($target instanceof RatePlan) {
                    $query->whereKeyNot($target->id);
                }

                $duplicate = $query->exists();

                if ($duplicate) {
                    $validator->errors()->add('code', 'Kode rate plan sudah digunakan pada scope tersebut.');
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
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
