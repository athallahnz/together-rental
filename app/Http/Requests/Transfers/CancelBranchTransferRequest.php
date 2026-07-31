<?php

namespace App\Http\Requests\Transfers;

use Illuminate\Foundation\Http\FormRequest;

class CancelBranchTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.cancel') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:5', 'max:3000']];
    }
}
