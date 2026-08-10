<?php

namespace App\Http\Requests\InventoryAudits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory-audits.create') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(
                    fn ($query) => $query
                        ->whereIn('id', $this->user()?->accessibleBranches()->select('id'))
                        ->where('is_active', true),
                ),
            ],
            'title' => ['required', 'string', 'min:5', 'max:150'],
            'scheduled_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
