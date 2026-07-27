<?php

namespace App\Http\Requests;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('productCategory');

        return Gate::allows('products.manage')
            && (! $category instanceof ProductCategory
                || $category->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $category = $this->route('productCategory');
        $categoryId = $category instanceof ProductCategory ? $category->id : null;

        return [
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('product_categories', 'id')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at'),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('product_categories', 'code')
                    ->where('company_id', $this->user()->company_id)
                    ->whereNull('deleted_at')
                    ->ignore($categoryId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $category = $this->route('productCategory');
                $parentId = $this->integer('parent_id') ?: null;

                if (! $category instanceof ProductCategory || $parentId === null) {
                    return;
                }

                if ($parentId === $category->id) {
                    $validator->errors()->add('parent_id', 'Kategori tidak dapat menjadi induknya sendiri.');

                    return;
                }

                $cursor = ProductCategory::query()->find($parentId);

                while ($cursor !== null) {
                    if ($cursor->id === $category->id) {
                        $validator->errors()->add(
                            'parent_id',
                            'Hierarki kategori membentuk siklus.',
                        );

                        return;
                    }

                    $cursor = $cursor->parent_id === null
                        ? null
                        : ProductCategory::query()->find($cursor->parent_id);
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->integer('sort_order'),
        ]);
    }
}
