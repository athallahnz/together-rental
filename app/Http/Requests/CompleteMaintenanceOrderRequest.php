<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompleteMaintenanceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('maintenance.manage') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', 'string', 'min:5', 'max:5000'],
            'actual_cost' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'asset_condition' => ['required', Rule::in(['good', 'damaged'])],
            'asset_disposition' => ['required', Rule::in(['available', 'maintenance', 'retired'])],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('asset_disposition') === 'available'
                    && $this->input('asset_condition') !== 'good') {
                    $validator->errors()->add(
                        'asset_condition',
                        'Aset hanya dapat tersedia kembali bila kondisinya baik.',
                    );
                }
            },
        ];
    }
}
