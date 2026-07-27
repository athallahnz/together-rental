<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return Gate::allows('roles.manage')
            && (! $role instanceof Role
                || ($role->company_id === $this->user()->company_id && ! $role->is_system));
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $target = $this->route('role');
        $targetId = $target instanceof Role ? $target->id : null;

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('roles', 'slug')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($targetId),
            ],
            'scope' => ['required', Rule::in(['company', 'branch'])],
            'permission_ids' => ['present', 'array'],
            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = trim((string) $this->input('name'));

        $this->merge([
            'name' => $name,
            'slug' => Str::slug((string) ($this->input('slug') ?: $name)),
            'permission_ids' => collect($this->input('permission_ids', []))
                ->map(fn (mixed $id): int => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ]);
    }
}
