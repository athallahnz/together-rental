<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('users.view');
        $actor = $request->user();
        $base = Employee::query()->where('company_id', $actor->company_id);
        $query = clone $base;
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $branchId = $request->integer('branch_id') ?: null;

        $employees = $query
            ->when($search !== '', function (Builder $employeeQuery) use ($search): void {
                $employeeQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($status, ['active', 'inactive', 'leave', 'terminated'], true),
                fn (Builder $employeeQuery) => $employeeQuery->where('status', $status),
            )
            ->when(
                $branchId !== null,
                fn (Builder $employeeQuery) => $employeeQuery
                    ->where('primary_branch_id', $branchId),
            )
            ->with([
                'primaryBranch:id,code,name',
                'position:id,code,name',
                'user:id,name,email,status',
            ])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'summary' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'withoutAccount' => (clone $base)->whereNull('user_id')->count(),
                'onLeave' => (clone $base)->where('status', 'leave')->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'branches' => Branch::query()
                ->where('company_id', $actor->company_id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']),
            'positions' => Position::query()
                ->where('company_id', $actor->company_id)
                ->withCount('employees')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'availableUsers' => User::query()
                ->where('company_id', $actor->company_id)
                ->whereDoesntHave('employee')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'status']),
            'permissions' => [
                'manage' => $actor->can('users.manage'),
                'viewRoles' => $actor->can('roles.view'),
            ],
        ]);
    }

    public function store(
        SaveEmployeeRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $employee = DB::transaction(function () use ($recorder, $request): Employee {
            $employee = Employee::query()->create([
                ...$request->validated(),
                'company_id' => $request->user()->company_id,
            ]);
            $recorder->record(
                $request,
                'employee.created',
                $employee,
                null,
                $this->auditValues($employee),
                $employee->primary_branch_id,
            );

            return $employee;
        });

        return to_route('employees.index')->with('toast', [
            'type' => 'success',
            'message' => "Karyawan {$employee->name} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SaveEmployeeRequest $request,
        Employee $employee,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardCompany($request, $employee);
        $oldValues = $this->auditValues($employee);

        DB::transaction(function () use (
            $employee,
            $oldValues,
            $recorder,
            $request,
        ): void {
            $employee->update($request->validated());
            $recorder->record(
                $request,
                'employee.updated',
                $employee,
                $oldValues,
                $this->auditValues($employee->fresh()),
                $employee->primary_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Data karyawan {$employee->name} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(
        Request $request,
        Employee $employee,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('users.manage');
        $this->guardCompany($request, $employee);
        $newStatus = $employee->status === 'active' ? 'inactive' : 'active';
        $oldValues = ['status' => $employee->status];

        $employee->update([
            'status' => $newStatus,
            'ended_at' => $newStatus === 'active' ? null : ($employee->ended_at ?? today()),
        ]);
        $recorder->record(
            $request,
            $newStatus === 'active' ? 'employee.activated' : 'employee.deactivated',
            $employee,
            $oldValues,
            ['status' => $newStatus],
            $employee->primary_branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Karyawan {$employee->name} berhasil ".
                ($newStatus === 'active' ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }

    private function guardCompany(Request $request, Employee $employee): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $employee->company_id === $request->user()->company_id,
            404,
        );
    }

    /** @return array<string, mixed> */
    private function auditValues(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'name' => $employee->name,
            'primary_branch_id' => $employee->primary_branch_id,
            'position_id' => $employee->position_id,
            'user_id' => $employee->user_id,
            'status' => $employee->status,
        ];
    }
}
