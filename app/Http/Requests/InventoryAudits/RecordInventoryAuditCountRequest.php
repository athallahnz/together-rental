<?php

namespace App\Http\Requests\InventoryAudits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordInventoryAuditCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory-audits.count') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'counted_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'observed_status' => ['nullable', Rule::in([
                'available', 'reserved', 'rented', 'maintenance', 'in_transit', 'lost', 'retired',
            ])],
            'observed_condition' => ['nullable', Rule::in(['good', 'fair', 'damaged'])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'capture_source' => ['nullable', 'required_with:photos', Rule::in(['camera'])],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
