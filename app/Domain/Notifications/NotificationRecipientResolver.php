<?php

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class NotificationRecipientResolver
{
    /**
     * @return Collection<int, User>
     */
    public function forPermission(
        int $companyId,
        ?int $branchId,
        string $permission,
        string $category,
        string $severity,
    ): Collection {
        $ids = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('users.company_id', $companyId)
            ->where('users.status', 'active')
            ->where('permissions.slug', $permission)
            ->where(function (Builder $query) use ($companyId): void {
                $query->whereNull('roles.company_id')->orWhere('roles.company_id', $companyId);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('role_user.expires_at')->orWhere('role_user.expires_at', '>', now());
            })
            ->when($branchId !== null, function (Builder $query) use ($branchId): void {
                $query->where(function (Builder $scope) use ($branchId): void {
                    $scope
                        ->where('roles.scope', 'company')
                        ->orWhereNull('role_user.branch_id')
                        ->orWhere('role_user.branch_id', $branchId);
                });
            })
            ->distinct()
            ->pluck('users.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->with('notificationPreference')
            ->whereIn('id', $ids)
            ->get()
            ->filter(static function (User $user) use ($category, $severity): bool {
                $muted = $user->notificationPreference->muted_categories ?? [];

                return $severity === 'critical' || ! in_array($category, $muted, true);
            })
            ->values();

        return $users;
    }
}
