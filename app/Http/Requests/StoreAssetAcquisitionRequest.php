<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assets.manage') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;

        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('company_id', $companyId)
                    ->where('tracking_type', 'serialized')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:25'],
            'acquisition_date' => ['required', 'date', 'before_or_equal:today'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'replacement_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'warranty_until' => ['nullable', 'date', 'after_or_equal:acquisition_date'],
            'serial_numbers' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
