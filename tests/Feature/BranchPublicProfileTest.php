<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BranchPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    private int $companyId;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->companyId = DB::table('companies')->insertGetId([
            'code' => 'TK',
            'name' => 'Together Kamera',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->branch = Branch::query()->create([
            'company_id' => $this->companyId,
            'code' => 'PNG',
            'name' => 'Together Kamera Ponorogo',
            'phone' => '085784771927',
            'address' => 'Jl. Brigjend Katamso Gg. VI No. 5, Ponorogo',
            'city' => 'Ponorogo',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    public function test_authorized_user_can_manage_public_branch_profile(): void
    {
        $user = $this->userWithPermission('branches.manage');

        $this->actingAs($user)
            ->get(route('branches.public-profile.edit', $this->branch))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('branches/public-profile')
                ->where('branch.code', 'PNG')
                ->where('profile.public_catalog_enabled', false));

        $this->actingAs($user)
            ->put(route('branches.public-profile.update', $this->branch), [
                'public_catalog_enabled' => true,
                'public_whatsapp' => '+62 857-8477-1927',
                'public_short_address' => 'Jl. Brigjend Katamso Gg. VI No. 5, Ponorogo',
                'public_maps_url' => 'https://maps.google.com/?q=Ponorogo',
                'public_instagram' => '@together_kamera',
                'public_opening_hours' => '09.00–21.00 WIB',
                'public_logo_path' => '/primary-logos.png',
                'public_hero_title' => 'Sewa alat kreatif tanpa ribet.',
                'public_hero_description' => 'Rental kamera dan perlengkapan kreatif di Ponorogo.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $this->branch->id,
            'key' => 'public_catalog_enabled',
            'value_type' => 'boolean',
            'value' => 'true',
            'is_public' => true,
        ]);
        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $this->branch->id,
            'key' => 'public_whatsapp',
            'value' => '"6285784771927"',
            'is_public' => true,
        ]);
    }

    public function test_inactive_branch_cannot_be_published(): void
    {
        $user = $this->userWithPermission('branches.manage');
        $this->branch->update(['is_active' => false]);

        $this->actingAs($user)
            ->from(route('branches.public-profile.edit', $this->branch))
            ->put(route('branches.public-profile.update', $this->branch), [
                'public_catalog_enabled' => true,
                'public_whatsapp' => '6285784771927',
                'public_short_address' => 'Ponorogo',
                'public_maps_url' => null,
                'public_instagram' => null,
                'public_opening_hours' => '09.00–21.00 WIB',
                'public_logo_path' => '/primary-logos.png',
                'public_hero_title' => 'Sewa alat kreatif tanpa ribet.',
                'public_hero_description' => null,
            ])
            ->assertRedirect(route('branches.public-profile.edit', $this->branch))
            ->assertSessionHasErrors('public_catalog_enabled');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $user = User::factory()->create([
            'company_id' => $this->companyId,
            'current_branch_id' => $this->branch->id,
        ]);

        $this->actingAs($user)
            ->get(route('branches.public-profile.edit', $this->branch))
            ->assertForbidden();
    }

    private function userWithPermission(string $permission): User
    {
        $user = User::factory()->create([
            'company_id' => $this->companyId,
            'current_branch_id' => $this->branch->id,
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'Manage branches',
            'slug' => $permission,
            'module' => 'branches',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Branch administrator',
            'slug' => 'branch-administrator',
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
            'assigned_by' => null,
            'assigned_at' => now(),
            'expires_at' => null,
        ]);

        return $user;
    }
}
