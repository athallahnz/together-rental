<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ApproveRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('refunds.approve') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
