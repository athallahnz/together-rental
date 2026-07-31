<?php

namespace App\Http\Requests\Transfers;

use App\Models\BranchTransfer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('transfer') instanceof BranchTransfer
            ? $this->user()?->can('transfers.update') === true
            : $this->user()?->can('transfers.create') === true;
    }

    /** @return array<string, list<mixed>> */
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $isUpdate = $this->route('transfer') instanceof BranchTransfer;

        return [
            'from_branch_id' => [
                'required', 'integer',
                Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'to_branch_id' => [
                'required', 'integer', 'different:from_branch_id',
                Rule::exists('branches', 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
            'planned_dispatch_at' => ['required', 'date'],
            'expected_arrival_at' => ['required', 'date', 'after:planned_dispatch_at'],
            'shipping_method' => ['nullable', Rule::in(['internal', 'courier', 'expedition', 'other'])],
            'courier_name' => ['nullable', 'string', 'max:150'],
            'courier_phone' => ['nullable', 'string', 'max:30'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'waybill_number' => ['nullable', 'string', 'max:100'],
            'seal_number' => ['nullable', 'string', 'max:100'],
            'shipping_notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at'),
            ],
            'items.*.asset_id' => ['nullable', 'integer', 'distinct', Rule::exists('assets', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.condition_before' => ['nullable', 'string', 'max:30'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
            'submit' => ['sometimes', 'boolean'],
            'approval_notes' => ['nullable', 'string', 'max:3000'],
            'lock_version' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'shipping_notes' => $this->filled('shipping_notes') ? trim((string) $this->input('shipping_notes')) : null,
            'submit' => $this->boolean('submit'),
        ]);
    }
}
