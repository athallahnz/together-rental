<?php

namespace Tests\Feature\Settings;

use App\Domain\Transfers\TransferSettings;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SettingsCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_management_can_open_center_but_company_identity_is_read_only(): void
    {
        [$user, $png, $mdn] = $this->companyUser('owner-management');

        $this->actingAs($user)
            ->get(route('settings-center.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/center')
                ->where('company.code', 'TK')
                ->where('permissions.company_view', true)
                ->where('permissions.company_manage', false)
                ->has('branches', 2)
                ->where('selectedBranchId', $png->id)
                ->where('selectedBranch.code', 'PNG'));

        $this->actingAs($user)
            ->put(route('settings-center.company.update'), [
                'name' => 'Should Not Change',
                'legal_name' => null,
                'tax_number' => null,
                'phone' => null,
                'email' => null,
                'address' => null,
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'Should Not Change']);
        $this->assertNotSame($png->id, $mdn->id);
    }

    public function test_super_admin_can_update_company_and_change_is_audited(): void
    {
        [$user] = $this->companyUser('super-admin');

        $this->actingAs($user)
            ->put(route('settings-center.company.update'), [
                'name' => 'Together Kamera Indonesia',
                'legal_name' => 'PT Together Kamera Indonesia',
                'tax_number' => '99.999.999.9-999.999',
                'phone' => '628123456789',
                'email' => 'hello@togetherkamera.test',
                'address' => 'Jl. Kamera No. 15',
                'timezone' => 'Asia/Jakarta',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'id' => $user->company_id,
            'name' => 'Together Kamera Indonesia',
            'legal_name' => 'PT Together Kamera Indonesia',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'company_id' => $user->company_id,
            'actor_id' => $user->id,
            'event' => 'company.settings.updated',
        ]);
    }

    public function test_branch_scoped_settings_role_only_receives_assigned_branch(): void
    {
        [$companyUser, $png, $mdn] = $this->companyUser('owner-management');
        $user = $this->customBranchUser($companyUser, $png, ['branches.manage']);

        $this->actingAs($user)
            ->get(route('settings-center.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('branches', 1)
                ->where('branches.0.id', $png->id)
                ->where('permissions.branches_manage', true));

        $this->actingAs($user)
            ->get(route('settings-center.index', ['branch_id' => $mdn->id]))
            ->assertNotFound();
    }

    public function test_center_reads_public_profile_and_transfer_policy_from_existing_sources(): void
    {
        [$user, $png] = $this->companyUser('super-admin');

        DB::table('branch_settings')->updateOrInsert(
            ['branch_id' => $png->id, 'key' => 'public_catalog_enabled'],
            [
                'value_type' => 'boolean',
                'value' => json_encode(true, JSON_THROW_ON_ERROR),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        DB::table('branch_settings')->updateOrInsert(
            ['branch_id' => $png->id, 'key' => 'public_whatsapp'],
            [
                'value_type' => 'string',
                'value' => json_encode('628123456789', JSON_THROW_ON_ERROR),
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
        app(TransferSettings::class)->update($png->id, [
            'dispatch_capture_mode' => 'camera_preferred',
            'receiving_capture_mode' => 'gallery_allowed',
            'dispatch_min_photos' => 2,
            'receiving_min_photos' => 3,
            'require_waybill' => false,
            'allow_gallery_override' => true,
        ]);

        $this->actingAs($user)
            ->get(route('settings-center.index', ['branch_id' => $png->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('publicProfile.catalog_enabled', true)
                ->where('publicProfile.configured_fields', 1)
                ->where('transferPolicy.dispatch_capture_mode', 'camera_preferred')
                ->where('transferPolicy.receiving_capture_mode', 'gallery_allowed')
                ->where('transferPolicy.dispatch_min_photos', 2)
                ->where('transferPolicy.receiving_min_photos', 3)
                ->where('transferPolicy.require_waybill', false)
                ->where('transferPolicy.allow_gallery_override', true));
    }

    public function test_user_without_administrative_settings_capability_is_forbidden(): void
    {
        [$companyUser, $png] = $this->companyUser('owner-management');
        $operator = $this->userForRole('rental-operator', $companyUser, $png);

        $this->actingAs($operator)
            ->get(route('settings-center.index'))
            ->assertForbidden();
    }

    /** @return array{0: User, 1: Branch, 2: Branch} */
    private function companyUser(string $roleSlug): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $companyId = (int) DB::table('companies')->where('code', 'TK')->value('id');
        $png = Branch::query()
            ->where('company_id', $companyId)
            ->where('code', 'PNG')
            ->firstOrFail();
        $mdn = Branch::query()->firstOrCreate(
            ['company_id' => $companyId, 'code' => 'MDN'],
            [
                'name' => 'Together Kamera Madiun',
                'city' => 'Madiun',
                'province' => 'Jawa Timur',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $png->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($png->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $role = Role::query()
            ->where('company_id', $companyId)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $user->roles()->attach($role->id, [
            'branch_id' => $role->scope === 'branch' ? $png->id : null,
            'assigned_at' => now(),
        ]);

        return [$user, $png, $mdn];
    }

    /** @param list<string> $permissions */
    private function customBranchUser(
        User $companyUser,
        Branch $branch,
        array $permissions,
    ): User {
        $permissions = array_values(array_unique([...$permissions, 'branches.switch']));

        $role = Role::query()->create([
            'company_id' => $companyUser->company_id,
            'name' => 'Settings Branch Role',
            'slug' => 'settings-branch-role',
            'scope' => 'branch',
            'is_system' => false,
        ]);
        $permissionIds = DB::table('permissions')
            ->whereIn('slug', $permissions)
            ->pluck('id')
            ->all();
        $role->permissions()->sync($permissionIds);

        $user = User::factory()->create([
            'company_id' => $companyUser->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    private function userForRole(string $roleSlug, User $companyUser, Branch $branch): User
    {
        $user = User::factory()->create([
            'company_id' => $companyUser->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $role = Role::query()
            ->where('company_id', $companyUser->company_id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $user->roles()->attach($role->id, [
            'branch_id' => $role->scope === 'branch' ? $branch->id : null,
            'assigned_at' => now(),
        ]);

        return $user;
    }
}
