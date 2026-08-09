<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelMaintenanceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
