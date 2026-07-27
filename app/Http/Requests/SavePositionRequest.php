<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePositionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $position = $this->route('position');

        return Gate::allows('users.manage')
            && (! $position instanceof Position
                || $position->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $target = $this->route('position');
        $targetId = $target instanceof Position ? $target->id : null;

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('positions', 'code')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($targetId),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }
}
