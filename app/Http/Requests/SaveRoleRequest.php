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

        if (! Gate::allows('roles.manage')) {
            return false;
        }

        if (! $role instanceof Role) {
            return true;
        }

        if ($role->company_id !== $this->user()->company_id) {
            return false;
        }

        return ! $role->is_system
            || $this->user()
                ->roles()
                ->where('roles.slug', 'super-admin')
                ->exists();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $target = $this->route('role');
        $targetId = $target instanceof Role ? $target->id : null;
        $isSystemRole = $target instanceof Role && $target->is_system;

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                ...($isSystemRole ? [Rule::in([$target->name])] : []),
            ],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                ...($isSystemRole ? [Rule::in([$target->slug])] : []),
                Rule::unique('roles', 'slug')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($targetId),
            ],
            'scope' => [
                'required',
                Rule::in($isSystemRole ? [$target->scope] : ['company', 'branch']),
            ],
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
