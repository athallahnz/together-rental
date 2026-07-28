<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reports.view') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['all', 'available', 'reserved', 'rented', 'maintenance', 'retired', 'lost']),
            ],
            'condition' => [
                'nullable',
                'string',
                Rule::in(['all', 'good', 'fair', 'poor', 'damaged', 'critical']),
            ],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
