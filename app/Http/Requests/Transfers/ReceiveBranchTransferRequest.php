<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceiveBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.receive') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receiving_notes' => ['nullable', 'string', 'max:5000'],
            'override_reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('branch_transfer_items', 'id')],
            'items.*.receiving_result' => ['required', Rule::in([
                'accepted_good', 'accepted_damaged', 'incomplete', 'missing', 'rejected',
            ])],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.condition' => ['required', 'string', 'max:30'],
            'items.*.checklist' => ['nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:3000'],
            'items.*.capture_source' => ['required', Rule::in(['camera', 'gallery', 'override'])],
            'items.*.photos' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}
