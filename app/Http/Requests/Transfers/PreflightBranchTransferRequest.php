<?php

namespace App\Http\Requests\Transfers;

class PreflightBranchTransferRequest extends SaveBranchTransferRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.create') === true
            || $this->user()?->can('transfers.update') === true;
    }
}
