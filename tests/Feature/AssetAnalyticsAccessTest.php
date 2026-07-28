<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssetAnalyticsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_reports_permission_can_open_asset_analytics(): void
    {
        $user = $this->userWithPermission('reports.view');

        $this->actingAs($user)
            ->get(route('reports.asset-analytics.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reports/asset-analytics')
                ->has('summary')
                ->has('assets')
                ->has('filters'));
    }

    public function test_user_without_reports_permission_is_forbidden(): void
    {
        [$user] = $this->createUserAndScope();

        $this->actingAs($user)
            ->get(route('reports.asset-analytics.index'))
            ->assertForbidden();
    }

    private function userWithPermission(string $permissionSlug): User
    {
        [$user, $companyId, $branchId] = $this->createUserAndScope();
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'View reports',
            'slug' => $permissionSlug,
            'module' => 'reports',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $companyId,
            'name' => 'Owner',
            'slug' => 'owner-test',
            'scope' => 'company',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permission_role')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'branch_id' => null,
            'assigned_at' => now(),
        ]);

        return $user->fresh();
    }

    /** @return array{0: User, 1: int, 2: int} */
    private function createUserAndScope(): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'code' => 'TK',
            'name' => 'Together Kamera',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'company_id' => $companyId,
            'code' => 'PNR',
            'name' => 'Ponorogo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branchId,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        DB::table('branch_user')->insert([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $companyId, $branchId];
    }
}
