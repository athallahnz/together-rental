<?php

namespace App\Domain\Access;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserAccessManager
{
    /**
     * @param  list<array{branch_id: int, role_id: int}>  $branchAccess
     */
    public function sync(
        User $user,
        ?int $companyRoleId,
        array $branchAccess,
        int $defaultBranchId,
        ?int $employeeId,
        int $assignedBy,
    ): void {
        $now = now();

        DB::table('role_user')->where('user_id', $user->id)->delete();

        $roleAssignments = [];

        if ($companyRoleId !== null) {
            $roleAssignments[] = [
                'role_id' => $companyRoleId,
                'user_id' => $user->id,
                'branch_id' => null,
                'assigned_by' => $assignedBy,
                'assigned_at' => $now,
                'expires_at' => null,
            ];
        }

        foreach ($branchAccess as $access) {
            $roleAssignments[] = [
                'role_id' => $access['role_id'],
                'user_id' => $user->id,
                'branch_id' => $access['branch_id'],
                'assigned_by' => $assignedBy,
                'assigned_at' => $now,
                'expires_at' => null,
            ];
        }

        if ($roleAssignments !== []) {
            DB::table('role_user')->insert($roleAssignments);
        }

        $branchIds = collect($branchAccess)
            ->pluck('branch_id')
            ->push($defaultBranchId)
            ->unique()
            ->values();
        $branchSync = $branchIds->mapWithKeys(
            fn (int $branchId): array => [
                $branchId => [
                    'is_default' => $branchId === $defaultBranchId,
                    'is_active' => $user->status === 'active',
                ],
            ],
        )->all();

        $user->branches()->sync($branchSync);
        $user->update(['current_branch_id' => $defaultBranchId]);

        Employee::query()
            ->where('user_id', $user->id)
            ->when(
                $employeeId !== null,
                fn ($query) => $query->whereKeyNot($employeeId),
            )
            ->update(['user_id' => null]);

        if ($employeeId !== null) {
            Employee::query()
                ->whereKey($employeeId)
                ->update(['user_id' => $user->id]);
        }
    }

    public function isSuperAdministrator(User $user): bool
    {
        return DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('roles.company_id', $user->company_id)
            ->where('roles.slug', 'super-admin')
            ->where('roles.scope', 'company')
            ->exists();
    }

    public function isLastActiveSuperAdministrator(User $user): bool
    {
        if (! $this->isSuperAdministrator($user)) {
            return false;
        }

        return ! DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('users', 'users.id', '=', 'role_user.user_id')
            ->where('roles.company_id', $user->company_id)
            ->where('roles.slug', 'super-admin')
            ->where('roles.scope', 'company')
            ->where('users.status', 'active')
            ->where('users.id', '!=', $user->id)
            ->exists();
    }

    public function roleIsSuperAdministrator(?int $roleId): bool
    {
        if ($roleId === null) {
            return false;
        }

        return DB::table('roles')
            ->where('id', $roleId)
            ->where('slug', 'super-admin')
            ->where('scope', 'company')
            ->exists();
    }
}
