<?php

namespace Tests\Feature\Branches;

use App\Domain\Branches\BranchProvisioner;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_view_company_branches(): void
    {
        [$user, $branch] = $this->superAdministrator();

        $this->actingAs($user)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('branches/index')
                ->where('summary.total', 1)
                ->where('summary.active', 1)
                ->where('branches.0.id', $branch->id)
                ->where('branches.0.code', 'PNG')
                ->where('permissions.manage', true)
                ->where('permissions.switch', true));
    }

    public function test_super_administrator_can_create_a_provisioned_branch(): void
    {
        [$user] = $this->superAdministrator();

        $this->actingAs($user)
            ->post(route('branches.store'), $this->branchPayload([
                'code' => 'mdo',
                'name' => 'Together Kamera Madiun',
                'city' => 'Madiun',
            ]))
            ->assertRedirect(route('branches.index'))
            ->assertSessionHasNoErrors();

        $branch = Branch::query()
            ->where('code', 'MDO')
            ->firstOrFail();

        $this->assertSame($user->company_id, $branch->company_id);

        $expectedSettingKeys = [
            'allow_cross_branch_return',
            'currency',
            'default_timezone',
            'legacy_source_system',
            'public_catalog_enabled',
            'public_hero_description',
            'public_hero_title',
            'public_instagram',
            'public_logo_path',
            'public_maps_url',
            'public_opening_hours',
            'public_short_address',
            'public_whatsapp',
            'require_customer_identity',
            'transfer_allow_gallery_override',
            'transfer_dispatch_capture_mode',
            'transfer_dispatch_min_photos',
            'transfer_receiving_capture_mode',
            'transfer_receiving_min_photos',
            'transfer_require_waybill',
        ];

        $actualSettingKeys = DB::table('branch_settings')
            ->where('branch_id', $branch->id)
            ->pluck('key')
            ->sort()
            ->values()
            ->all();

        $this->assertSame($expectedSettingKeys, $actualSettingKeys);

        $this->assertDatabaseHas('branch_settings', [
            'branch_id' => $branch->id,
            'key' => 'public_catalog_enabled',
            'value_type' => 'boolean',
            'is_public' => true,
        ]);

        $this->assertSame(
            9,
            DB::table('number_sequences')
                ->where('branch_id', $branch->id)
                ->count(),
        );

        $this->assertDatabaseHas('number_sequences', [
            'branch_id' => $branch->id,
            'document_type' => 'rental',
            'prefix' => 'MDO-RNT',
        ]);

        $this->assertDatabaseHas('cash_registers', [
            'branch_id' => $branch->id,
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $branch->id,
            'actor_id' => $user->id,
            'event' => 'branch.created',
        ]);
    }

    public function test_duplicate_branch_code_is_rejected_within_the_company(): void
    {
        [$user] = $this->superAdministrator();

        $this->actingAs($user)
            ->post(route('branches.store'), $this->branchPayload([
                'code' => 'png',
                'name' => 'Duplicate Ponorogo',
            ]))
            ->assertSessionHasErrors('code');

        $this->assertSame(
            1,
            Branch::query()
                ->where('company_id', $user->company_id)
                ->where('code', 'PNG')
                ->count(),
        );
    }

    public function test_user_can_switch_to_an_accessible_active_branch(): void
    {
        [$user] = $this->superAdministrator();

        $branch = Branch::query()->create([
            ...$this->branchPayload([
                'code' => 'MDO',
                'name' => 'Together Kamera Madiun',
            ]),
            'company_id' => $user->company_id,
        ]);

        app(BranchProvisioner::class)->provision($branch);

        $user->branches()->attach($branch->id, [
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->post(route('branches.switch', $branch))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $branch->id,
            $user->fresh()->current_branch_id,
        );

        $this->assertDatabaseHas('activity_logs', [
            'branch_id' => $branch->id,
            'actor_id' => $user->id,
            'event' => 'branch.switched',
        ]);
    }

    public function test_current_branch_cannot_be_deactivated(): void
    {
        [$user, $currentBranch] = $this->superAdministrator();

        Branch::query()->create([
            ...$this->branchPayload([
                'code' => 'MDO',
                'name' => 'Together Kamera Madiun',
            ]),
            'company_id' => $user->company_id,
        ]);

        $this->actingAs($user)
            ->patch(route('branches.toggle-status', $currentBranch))
            ->assertSessionHasErrors('branch');

        $this->assertTrue($currentBranch->fresh()->is_active);
    }

    public function test_branch_code_with_operational_data_cannot_be_changed(): void
    {
        [$user, $branch] = $this->superAdministrator();

        DB::table('customers')->insert([
            'company_id' => $user->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'CUST-001',
            'name' => 'Pelanggan Operasional',
            'is_member' => false,
            'status' => 'active',
            'risk_level' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->put(route('branches.update', $branch), $this->branchPayload([
                'code' => 'PNR',
                'name' => $branch->name,
                'city' => $branch->city,
                'province' => $branch->province,
            ]))
            ->assertSessionHasErrors('code');

        $this->assertSame('PNG', $branch->fresh()->code);
    }

    public function test_user_without_branch_permission_cannot_open_the_module(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('branches.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Branch}
     */
    private function superAdministrator(): array
    {
        $this->seed(RentalFoundationSeeder::class);

        $companyId = (int) DB::table('companies')
            ->where('code', 'TK')
            ->value('id');

        $branch = Branch::query()
            ->where('company_id', $companyId)
            ->where('code', 'PNG')
            ->firstOrFail();

        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branch->id,
        ]);

        $roleId = (int) DB::table('roles')
            ->where('company_id', $companyId)
            ->where('slug', 'super-admin')
            ->value('id');

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

        return [$user, $branch];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function branchPayload(array $overrides = []): array
    {
        return [
            'code' => 'MDO',
            'name' => 'Together Kamera Madiun',
            'phone' => '081234567890',
            'email' => 'madiun@togetherkamera.test',
            'address' => 'Jalan Contoh 1',
            'village' => 'Kartoharjo',
            'district' => 'Kartoharjo',
            'city' => 'Madiun',
            'province' => 'Jawa Timur',
            'postal_code' => '63117',
            'timezone' => 'Asia/Jakarta',
            'opened_at' => '2026-07-27',
            'is_active' => true,
            ...$overrides,
        ];
    }
}
