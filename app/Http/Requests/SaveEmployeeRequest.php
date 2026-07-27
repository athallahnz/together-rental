<?php

namespace App\Http\Requests;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $employee = $this->route('employee');

        return Gate::allows('users.manage')
            && (! $employee instanceof Employee
                || $employee->company_id === $this->user()->company_id);
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $target = $this->route('employee');
        $targetId = $target instanceof Employee ? $target->id : null;
        $companyId = $this->user()->company_id;

        return [
            'employee_number' => [
                'required',
                'string',
                'max:40',
                Rule::unique('employees', 'employee_number')
                    ->where('company_id', $companyId)
                    ->ignore($targetId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'identity_number' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'joined_at' => ['nullable', 'date_format:Y-m-d'],
            'ended_at' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:joined_at'],
            'status' => [
                'required',
                Rule::in(['active', 'inactive', 'leave', 'terminated']),
            ],
            'primary_branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'position_id' => [
                'nullable',
                'integer',
                Rule::exists('positions', 'id')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $userId = $this->integer('user_id') ?: null;

                if ($userId === null) {
                    return;
                }

                $target = $this->route('employee');
                $targetId = $target instanceof Employee ? $target->id : null;
                $linked = Employee::query()
                    ->where('user_id', $userId)
                    ->when($targetId !== null, fn ($query) => $query->whereKeyNot($targetId))
                    ->exists();

                if ($linked) {
                    $validator->errors()->add(
                        'user_id',
                        'Akun tersebut sudah terhubung dengan karyawan lain.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employee_number' => mb_strtoupper(trim((string) $this->input('employee_number'))),
            'name' => trim((string) $this->input('name')),
            'email' => $this->filled('email')
                ? mb_strtolower(trim((string) $this->input('email')))
                : null,
            'user_id' => $this->filled('user_id') ? $this->integer('user_id') : null,
            'primary_branch_id' => $this->filled('primary_branch_id')
                ? $this->integer('primary_branch_id')
                : null,
            'position_id' => $this->filled('position_id')
                ? $this->integer('position_id')
                : null,
        ]);
    }
}
