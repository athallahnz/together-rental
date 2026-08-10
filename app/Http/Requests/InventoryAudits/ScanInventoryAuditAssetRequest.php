<?php

namespace App\Http\Requests\InventoryAudits;

use Illuminate\Foundation\Http\FormRequest;

class ScanInventoryAuditAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory-audits.count') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => trim($this->string('code')->toString())]);
    }
}
