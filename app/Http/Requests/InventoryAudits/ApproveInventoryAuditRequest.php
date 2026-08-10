<?php

namespace App\Http\Requests\InventoryAudits;

use Illuminate\Foundation\Http\FormRequest;

class ApproveInventoryAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory-audits.approve') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
