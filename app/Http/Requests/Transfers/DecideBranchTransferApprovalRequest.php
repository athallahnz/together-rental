<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideBranchTransferApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.approve') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'side' => ['required', Rule::in(['origin', 'destination'])],
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'notes' => [
                Rule::requiredIf(fn (): bool => $this->input('decision') === 'rejected'),
                'nullable', 'string', 'min:5', 'max:3000',
            ],
            'revision_number' => ['required', 'integer', 'min:1'],
        ];
    }
}
