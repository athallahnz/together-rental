<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Validation\Rule;

class PreflightBranchTransferRequest extends SaveBranchTransferRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.create') === true
            || $this->user()?->can('transfers.update') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'context_transfer_id' => [
                'nullable',
                'integer',
                Rule::exists('branch_transfers', 'id')
                    ->where('company_id', $this->user()?->company_id),
            ],
        ];
    }
}
