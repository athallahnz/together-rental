<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class RejectRefundRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => $this->string('reason')->trim()->toString(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('refunds.approve') === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }
}
