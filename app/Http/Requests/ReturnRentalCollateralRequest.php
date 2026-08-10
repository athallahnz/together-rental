<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReturnRentalCollateralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('rentals.return') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
        ];
    }
}
