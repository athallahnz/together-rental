<?php

namespace App\Http\Requests\InventoryAudits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveInventoryAuditFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory-audits.resolve') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution_action' => ['required', Rule::in([
                'accept_no_change',
                'update_condition',
                'create_maintenance',
                'mark_lost',
                'transfer_required',
                'status_review_required',
                'adjust_quantity',
            ])],
            'resolution_notes' => ['required', 'string', 'min:5', 'max:5000'],
        ];
    }
}
