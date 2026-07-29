<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReopenRentalReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rentals.reopen_return') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rental_return_id' => ['required', 'integer', 'exists:rental_returns,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
