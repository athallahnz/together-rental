<?php

namespace App\Http\Requests\InventoryAudits;

use Illuminate\Foundation\Http\FormRequest;

class CancelInventoryAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory-audits.cancel') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }
}
