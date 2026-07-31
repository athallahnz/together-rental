<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DispatchBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.dispatch') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shipping_method' => ['required', Rule::in(['internal', 'courier', 'expedition', 'other'])],
            'courier_name' => ['required', 'string', 'max:150'],
            'courier_phone' => ['nullable', 'string', 'max:30'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'seal_number' => ['nullable', 'string', 'max:100'],
            'shipping_notes' => ['nullable', 'string', 'max:5000'],
            'waybill_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
            'inspections' => ['required', 'array', 'min:1'],
            'inspections.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('branch_transfer_items', 'id')],
            'inspections.*.condition' => ['required', 'string', 'max:30'],
            'inspections.*.checklist' => ['nullable', 'array'],
            'inspections.*.notes' => ['nullable', 'string', 'max:3000'],
            'inspections.*.capture_source' => ['required', Rule::in(['camera', 'gallery', 'override'])],
            'inspections.*.photos' => ['required', 'array', 'min:1', 'max:10'],
            'inspections.*.photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
