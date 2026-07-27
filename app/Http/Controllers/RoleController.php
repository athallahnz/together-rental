<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SaveRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('roles.view');
        $actor = $request->user();

        return Inertia::render('roles/index', [
            'roles' => Role::query()
                ->where('company_id', $actor->company_id)
                ->with(['permissions:id,name,slug,module'])
                ->withCount('users')
                ->orderByDesc('is_system')
                ->orderBy('scope')
                ->orderBy('name')
                ->get(),
            'permissionGroups' => Permission::query()
                ->orderBy('module')
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'module', 'description'])
                ->groupBy('module'),
            'permissions' => [
                'manage' => $actor->can('roles.manage'),
                'manageUsers' => $actor->can('users.manage'),
            ],
        ]);
    }

    public function store(
        SaveRoleRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();

        $role = DB::transaction(function () use ($recorder, $request, $validated): Role {
            $role = Role::query()->create([
                'company_id' => $request->user()->company_id,
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'scope' => $validated['scope'],
                'is_system' => false,
            ]);
            $role->permissions()->sync(
                $this->normalizedPermissions(
                    $validated['scope'],
                    $validated['permission_ids'],
                ),
            );
            $recorder->record(
                $request,
                'role.created',
                $role,
                null,
                $this->auditValues($role),
            );

            return $role;
        });

        return to_route('roles.index')->with('toast', [
            'type' => 'success',
            'message' => "Role {$role->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveRoleRequest $request,
        Role $role,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardCustomRole($request, $role);
        $validated = $request->validated();

        if ($role->scope !== $validated['scope'] && $role->users()->exists()) {
            throw ValidationException::withMessages([
                'scope' => 'Scope role yang sudah digunakan tidak dapat diubah.',
            ]);
        }

        $oldValues = $this->auditValues($role);

        DB::transaction(function () use (
            $oldValues,
            $recorder,
            $request,
            $role,
            $validated,
        ): void {
            $role->update([
                'name' => $validated['name'],
                'slug' => $validated['slug'],
                'scope' => $validated['scope'],
            ]);
            $role->permissions()->sync(
                $this->normalizedPermissions(
                    $validated['scope'],
                    $validated['permission_ids'],
                ),
            );
            $recorder->record(
                $request,
                'role.updated',
                $role,
                $oldValues,
                $this->auditValues($role->fresh()),
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Role {$role->name} berhasil diperbarui.",
        ]);
    }

    /**
     * @param  list<int>  $permissionIds
     * @return list<int>
     */
    private function normalizedPermissions(string $scope, array $permissionIds): array
    {
        if ($scope !== 'branch') {
            return $permissionIds;
        }

        $switchPermissionId = Permission::query()
            ->where('slug', 'branches.switch')
            ->value('id');

        return collect($permissionIds)
            ->when($switchPermissionId !== null, fn ($ids) => $ids->push((int) $switchPermissionId))
            ->unique()
            ->values()
            ->all();
    }

    private function guardCustomRole(Request $request, Role $role): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $role->company_id === $request->user()->company_id,
            404,
        );

        abort_if($role->is_system, 403, 'Role sistem tidak dapat diubah.');
    }

    /** @return array<string, mixed> */
    private function auditValues(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'scope' => $role->scope,
            'permission_ids' => $role->permissions()->pluck('permissions.id')->all(),
        ];
    }
}
