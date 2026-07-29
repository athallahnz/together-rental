<?php

namespace Tests\Feature\Access;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_open_access_management_pages(): void
    {
        [$user] = $this->superAdministrator();

        $this->actingAs($user)
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('users/index')
                ->where('permissions.manage', true)
                ->where('summary.total', 1));

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('employees/index')
                ->where('permissions.manage', true));

        $this->actingAs($user)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('roles/index')
                ->where('permissions.manage', true)
                ->has('roles', 6));
    }

    public function test_super_administrator_can_create_a_branch_scoped_user(): void
    {
        [$actor, $branch] = $this->superAdministrator();
        $role = Role::query()
            ->where('company_id', $actor->company_id)
            ->where('slug', 'branch-manager')
            ->firstOrFail();

        $this->actingAs($actor)
            ->post(route('users.store'), [
                'name' => 'Manager Ponorogo',
                'email' => 'manager.png@example.test',
                'password' => 'SecurePassword#2026',
                'password_confirmation' => 'SecurePassword#2026',
                'status' => 'active',
                'employee_id' => null,
                'company_role_id' => null,
                'default_branch_id' => $branch->id,
                'branch_access' => [
                    [
                        'branch_id' => $branch->id,
                        'role_id' => $role->id,
                    ],
                ],
            ])
            ->assertRedirect(route('users.index'))
            ->assertSessionHasNoErrors();

        $user = User::query()
            ->where('email', 'manager.png@example.test')
            ->firstOrFail();

        $this->assertSame($actor->company_id, $user->company_id);
        $this->assertSame($branch->id, $user->current_branch_id);
        $this->assertTrue(Hash::check('SecurePassword#2026', $user->password));
        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('role_user', [
            'role_id' => $role->id,
            'user_id' => $user->id,
            'branch_id' => $branch->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $actor->id,
            'subject_id' => $user->id,
            'event' => 'user.created',
        ]);
    }

    public function test_current_user_cannot_edit_or_deactivate_their_own_access(): void
    {
        [$actor, $branch] = $this->superAdministrator();
        $role = Role::query()
            ->where('company_id', $actor->company_id)
            ->where('slug', 'super-admin')
            ->firstOrFail();

        $this->actingAs($actor)
            ->put(route('users.update', $actor), [
                'name' => 'Changed Name',
                'email' => $actor->email,
                'password' => '',
                'password_confirmation' => '',
                'status' => 'active',
                'employee_id' => null,
                'company_role_id' => $role->id,
                'default_branch_id' => $branch->id,
                'branch_access' => [],
            ])
            ->assertSessionHasErrors('user');

        $this->actingAs($actor)
            ->patch(route('users.toggle-status', $actor))
            ->assertSessionHasErrors('user');

        $this->assertSame('active', $actor->fresh()->status);
        $this->assertNotSame('Changed Name', $actor->fresh()->name);
    }

    public function test_last_active_super_administrator_is_protected(): void
    {
        [$superAdministrator, $branch] = $this->superAdministrator();
        $manager = $this->companyAccessManager(
            (int) $superAdministrator->company_id,
            $branch,
        );

        $this->actingAs($manager)
            ->patch(
                route('users.toggle-status', $superAdministrator),
            )
            ->assertSessionHasErrors('user');

        $this->assertSame('active', $superAdministrator->fresh()->status);
    }

    public function test_employee_position_and_login_account_can_be_linked(): void
    {
        [$actor, $branch] = $this->superAdministrator();
        $login = User::factory()->create([
            'company_id' => $actor->company_id,
            'current_branch_id' => $branch->id,
        ]);

        $this->actingAs($actor)
            ->post(route('positions.store'), [
                'code' => 'OPS',
                'name' => 'Rental Operator',
                'description' => 'Petugas transaksi rental.',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $position = Position::query()->where('code', 'OPS')->firstOrFail();

        $this->actingAs($actor)
            ->post(route('employees.store'), [
                'employee_number' => 'EMP-001',
                'name' => 'Operator Rental',
                'identity_number' => null,
                'gender' => 'female',
                'phone' => '081234567890',
                'email' => 'operator@example.test',
                'address' => 'Ponorogo',
                'birth_place' => null,
                'birth_date' => null,
                'joined_at' => '2026-07-27',
                'ended_at' => null,
                'status' => 'active',
                'primary_branch_id' => $branch->id,
                'position_id' => $position->id,
                'user_id' => $login->id,
            ])
            ->assertSessionHasNoErrors();

        $employee = Employee::query()
            ->where('employee_number', 'EMP-001')
            ->firstOrFail();

        $this->assertSame($actor->company_id, $employee->company_id);
        $this->assertSame($branch->id, $employee->primary_branch_id);
        $this->assertSame($position->id, $employee->position_id);
        $this->assertSame($login->id, $employee->user_id);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $actor->id,
            'subject_id' => $employee->id,
            'event' => 'employee.created',
        ]);
    }

    public function test_position_used_by_an_active_employee_cannot_be_deactivated(): void
    {
        [$actor, $branch] = $this->superAdministrator();
        $position = Position::query()->create([
            'company_id' => $actor->company_id,
            'code' => 'CS',
            'name' => 'Customer Service',
            'is_active' => true,
        ]);
        Employee::query()->create([
            'company_id' => $actor->company_id,
            'primary_branch_id' => $branch->id,
            'position_id' => $position->id,
            'employee_number' => 'EMP-CS-01',
            'name' => 'Customer Service Aktif',
            'status' => 'active',
        ]);

        $this->actingAs($actor)
            ->patch(route('positions.toggle-status', $position))
            ->assertSessionHasErrors('position');

        $this->assertTrue($position->fresh()->is_active);
    }

    public function test_custom_branch_role_always_receives_branch_switch_permission(): void
    {
        [$actor] = $this->superAdministrator();
        $rentalsViewId = (int) DB::table('permissions')
            ->where('slug', 'rentals.view')
            ->value('id');

        $this->actingAs($actor)
            ->post(route('roles.store'), [
                'name' => 'Rental Reviewer',
                'slug' => 'rental-reviewer',
                'scope' => 'branch',
                'permission_ids' => [$rentalsViewId],
            ])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHasNoErrors();

        $role = Role::query()
            ->where('company_id', $actor->company_id)
            ->where('slug', 'rental-reviewer')
            ->firstOrFail();

        $this->assertTrue($role->permissions()->where('slug', 'rentals.view')->exists());
        $this->assertTrue($role->permissions()->where('slug', 'branches.switch')->exists());
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $actor->id,
            'subject_id' => $role->id,
            'event' => 'role.created',
        ]);
    }

    public function test_super_administrator_can_update_system_role_permissions(): void
    {
        [$actor] = $this->superAdministrator();

        $systemRole = Role::query()
            ->where('company_id', $actor->company_id)
            ->where('slug', 'branch-manager')
            ->firstOrFail();

        $permission = Permission::query()
            ->where('slug', 'reports.view')
            ->firstOrFail();

        $this->actingAs($actor)
            ->put(route('roles.update', $systemRole), [
                'name' => $systemRole->name,
                'slug' => $systemRole->slug,
                'scope' => $systemRole->scope,
                'permission_ids' => [$permission->id],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $systemRole->refresh();

        $this->assertSame('Branch Manager', $systemRole->name);
        $this->assertSame('branch-manager', $systemRole->slug);
        $this->assertTrue(
            $systemRole->permissions()
                ->where('permissions.id', $permission->id)
                ->exists(),
        );

        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $actor->id,
            'subject_id' => $systemRole->id,
            'event' => 'role.updated',
        ]);
    }

    public function test_system_role_identity_cannot_be_changed(): void
    {
        [$actor] = $this->superAdministrator();

        $systemRole = Role::query()
            ->where('company_id', $actor->company_id)
            ->where('slug', 'branch-manager')
            ->firstOrFail();

        $originalName = $systemRole->name;
        $originalSlug = $systemRole->slug;
        $originalScope = $systemRole->scope;

        $this->actingAs($actor)
            ->put(route('roles.update', $systemRole), [
                'name' => 'Changed System Role',
                'slug' => 'changed-system-role',
                'scope' => $originalScope === 'branch' ? 'company' : 'branch',
                'permission_ids' => $systemRole
                    ->permissions()
                    ->pluck('permissions.id')
                    ->all(),
            ])
            ->assertSessionHasErrors(['name', 'slug', 'scope']);

        $systemRole->refresh();

        $this->assertSame($originalName, $systemRole->name);
        $this->assertSame($originalSlug, $systemRole->slug);
        $this->assertSame($originalScope, $systemRole->scope);
    }

    public function test_inactive_user_cannot_authenticate_or_keep_an_active_session(): void
    {
        $user = User::factory()->create(['status' => 'inactive']);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /**
     * @return array{0: User, 1: Branch}
     */
    private function superAdministrator(): array
    {
        $this->seed(RentalFoundationSeeder::class);

        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $branch = Branch::query()
            ->where('company_id', $companyId)
            ->where('code', 'PNG')
            ->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $roleId = (int) DB::table('roles')
            ->where('company_id', $companyId)
            ->where('slug', 'super-admin')
            ->value('id');
        $this->assignAccess($user, $branch, $roleId);

        return [$user, $branch];
    }

    private function companyAccessManager(int $companyId, Branch $branch): User
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['users.view', 'users.manage'])
            ->pluck('id')
            ->all();
        $role = Role::query()->create([
            'company_id' => $companyId,
            'name' => 'Access Manager',
            'slug' => 'access-manager',
            'scope' => 'company',
            'is_system' => false,
        ]);
        $role->permissions()->sync($permissionIds);
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->assignAccess($user, $branch, $role->id);

        return $user;
    }

    private function assignAccess(User $user, Branch $branch, int $roleId): void
    {
        $now = now();

        DB::table('branch_user')->insert([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'branch_id' => null,
            'assigned_by' => null,
            'assigned_at' => $now,
            'expires_at' => null,
        ]);
    }
}
