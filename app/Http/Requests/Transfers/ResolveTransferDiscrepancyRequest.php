<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveTransferDiscrepancyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.resolve_discrepancy') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution_action' => ['required', Rule::in(['accept_at_destination', 'return_to_origin', 'mark_lost'])],
            'notes' => ['required', 'string', 'min:5', 'max:3000'],
        ];
    }
}
