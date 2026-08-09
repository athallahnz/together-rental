<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return Gate::allows('users.manage')
            && (! $user instanceof User || $user->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->id : null;
        $companyId = $this->user()->company_id;

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
            ],
            'password' => [
                $targetId === null ? 'required' : 'nullable',
                'confirmed',
                Password::defaults(),
            ],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'company_role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')
                    ->where('company_id', $companyId)
                    ->where('scope', 'company'),
            ],
            'default_branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'branch_access' => ['present', 'array'],
            'branch_access.*.branch_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('branches', 'id')
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'branch_access.*.role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')
                    ->where('company_id', $companyId)
                    ->where('scope', 'branch'),
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $companyRoleId = $this->integer('company_role_id') ?: null;
                $branchAccessInput = $this->input('branch_access', []);
                $branchAccess = is_array($branchAccessInput) ? $branchAccessInput : [];

                if ($companyRoleId === null && $branchAccess === []) {
                    $validator->errors()->add(
                        'branch_access',
                        'Pilih minimal satu role perusahaan atau akses cabang.',
                    );
                }

                if (
                    $companyRoleId === null
                    && ! collect($branchAccess)->contains(
                        fn (mixed $access): bool => is_array($access)
                            && (int) ($access['branch_id'] ?? 0) === $this->integer('default_branch_id'),
                    )
                ) {
                    $validator->errors()->add(
                        'default_branch_id',
                        'Cabang default harus termasuk dalam akses cabang pengguna.',
                    );
                }

                $employeeId = $this->integer('employee_id') ?: null;

                if ($employeeId === null) {
                    return;
                }

                $employee = Employee::query()->find($employeeId);
                $target = $this->route('user');
                $targetId = $target instanceof User ? $target->id : null;

                if ($employee?->user_id !== null && $employee->user_id !== $targetId) {
                    $validator->errors()->add(
                        'employee_id',
                        'Karyawan tersebut sudah terhubung dengan akun lain.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $branchAccessInput = $this->input('branch_access', []);
        $branchAccess = is_array($branchAccessInput) ? $branchAccessInput : [];

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'employee_id' => $this->filled('employee_id')
                ? $this->integer('employee_id')
                : null,
            'company_role_id' => $this->filled('company_role_id')
                ? $this->integer('company_role_id')
                : null,
            'branch_access' => collect($branchAccess)
                ->filter(fn (mixed $access): bool => is_array($access))
                ->map(fn (array $access): array => [
                    'branch_id' => (int) ($access['branch_id'] ?? 0),
                    'role_id' => (int) ($access['role_id'] ?? 0),
                ])
                ->values()
                ->all(),
        ]);
    }
}
