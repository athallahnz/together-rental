<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Access\UserAccessManager;
use App\Http\Requests\SaveUserRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('users.view');
        $actor = $request->user();
        $base = User::query()->where('company_id', $actor->company_id);
        $query = clone $base;
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $branchId = $request->integer('branch_id') ?: null;

        $users = $query
            ->when($search !== '', function (Builder $userQuery) use ($search): void {
                $userQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn (Builder $employeeQuery) => $employeeQuery
                            ->where('employee_number', 'like', "%{$search}%"));
                });
            })
            ->when(
                in_array($status, ['active', 'inactive', 'suspended'], true),
                fn (Builder $userQuery) => $userQuery->where('status', $status),
            )
            ->when(
                $branchId !== null,
                fn (Builder $userQuery) => $userQuery->whereHas(
                    'branches',
                    fn (Builder $branchQuery) => $branchQuery->whereKey($branchId),
                ),
            )
            ->with([
                'currentBranch:id,code,name',
                'branches:id,code,name,is_active',
                'employee:id,user_id,employee_number,name,primary_branch_id,position_id,status',
                'employee.primaryBranch:id,code,name',
                'employee.position:id,code,name',
                'roles:id,name,slug,scope,is_system',
            ])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('users/index', [
            'users' => $users,
            'summary' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'inactive' => (clone $base)
                    ->whereIn('status', ['inactive', 'suspended'])
                    ->count(),
                'twoFactor' => (clone $base)
                    ->whereNotNull('two_factor_confirmed_at')
                    ->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'branches' => Branch::query()
                ->where('company_id', $actor->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'roles' => Role::query()
                ->where('company_id', $actor->company_id)
                ->orderBy('scope')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'scope', 'is_system']),
            'availableEmployees' => Employee::query()
                ->where('company_id', $actor->company_id)
                ->where(function (Builder $employeeQuery): void {
                    $employeeQuery
                        ->whereNull('user_id')
                        ->orWhereNotNull('user_id');
                })
                ->with('user:id,name,email')
                ->orderBy('name')
                ->get([
                    'id',
                    'user_id',
                    'employee_number',
                    'name',
                    'primary_branch_id',
                    'status',
                ]),
            'permissions' => [
                'manage' => $actor->can('users.manage'),
                'viewRoles' => $actor->can('roles.view'),
            ],
            'currentUserId' => $actor->id,
        ]);
    }

    public function store(
        SaveUserRequest $request,
        UserAccessManager $accessManager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $request->user();

        $user = DB::transaction(function () use (
            $accessManager,
            $actor,
            $recorder,
            $request,
            $validated,
        ): User {
            $user = User::query()->create([
                'company_id' => $actor->company_id,
                'current_branch_id' => $validated['default_branch_id'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'status' => $validated['status'],
                'email_verified_at' => now(),
            ]);

            $accessManager->sync(
                $user,
                $validated['company_role_id'],
                $validated['branch_access'],
                $validated['default_branch_id'],
                $validated['employee_id'],
                $actor->id,
            );
            $recorder->record(
                $request,
                'user.created',
                $user,
                null,
                $this->auditValues($user),
            );

            return $user;
        });

        return to_route('users.index')->with('toast', [
            'type' => 'success',
            'message' => "Akun {$user->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveUserRequest $request,
        User $user,
        UserAccessManager $accessManager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardCompany($request, $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'Gunakan menu Pengaturan Profil untuk akun Anda sendiri. Hak akses akun aktif tidak dapat diubah dari modul ini.',
            ]);
        }

        $validated = $request->validated();

        if (
            $accessManager->isLastActiveSuperAdministrator($user)
            && (
                $validated['status'] !== 'active'
                || ! $accessManager->roleIsSuperAdministrator($validated['company_role_id'])
            )
        ) {
            throw ValidationException::withMessages([
                'company_role_id' => 'Perusahaan wajib memiliki minimal satu Super Administrator aktif.',
            ]);
        }

        $oldValues = $this->auditValues($user);

        DB::transaction(function () use (
            $accessManager,
            $recorder,
            $request,
            $user,
            $validated,
            $oldValues,
        ): void {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'status' => $validated['status'],
            ];

            if (filled($validated['password'] ?? null)) {
                $attributes['password'] = $validated['password'];
            }

            $user->update($attributes);
            $accessManager->sync(
                $user,
                $validated['company_role_id'],
                $validated['branch_access'],
                $validated['default_branch_id'],
                $validated['employee_id'],
                $request->user()->id,
            );
            $recorder->record(
                $request,
                'user.updated',
                $user,
                $oldValues,
                $this->auditValues($user->fresh()),
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Akun {$user->name} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(
        Request $request,
        User $user,
        UserAccessManager $accessManager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('users.manage');
        $this->guardCompany($request, $user);

        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.',
            ]);
        }

        if (
            $user->status === 'active'
            && $accessManager->isLastActiveSuperAdministrator($user)
        ) {
            throw ValidationException::withMessages([
                'user' => 'Super Administrator aktif terakhir tidak dapat dinonaktifkan.',
            ]);
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';

        DB::transaction(function () use ($newStatus, $recorder, $request, $user): void {
            $oldValues = ['status' => $user->status];
            $user->update(['status' => $newStatus]);
            DB::table('branch_user')
                ->where('user_id', $user->id)
                ->update([
                    'is_active' => $newStatus === 'active',
                    'updated_at' => now(),
                ]);
            $recorder->record(
                $request,
                $newStatus === 'active' ? 'user.activated' : 'user.deactivated',
                $user,
                $oldValues,
                ['status' => $newStatus],
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Akun {$user->name} berhasil ".
                ($newStatus === 'active' ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }

    private function guardCompany(Request $request, User $user): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $user->company_id === $request->user()->company_id,
            404,
        );
    }

    /** @return array<string, mixed> */
    private function auditValues(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'current_branch_id' => $user->current_branch_id,
        ];
    }
}
