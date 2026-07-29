<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') === true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')->where(
                    fn ($query) => $query
                        ->whereIn(
                            'current_branch_id',
                            $this->user()?->accessibleBranches()->select('id'),
                        )
                        ->where('is_active', true),
                ),
            ],
            'type' => ['required', Rule::in(['repair', 'service', 'inspection', 'cleaning', 'other'])],
            'problem_description' => ['required', 'string', 'min:5', 'max:5000'],
            'vendor_name' => ['nullable', 'string', 'max:150'],
            'estimated_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ];
    }
}
