<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RentalDatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rental_management_v2_tables_are_available(): void
    {
        $tables = [
            'companies',
            'branches',
            'branch_settings',
            'positions',
            'employees',
            'roles',
            'permissions',
            'branch_user',
            'role_user',
            'number_sequences',
            'customers',
            'customer_identities',
            'customer_addresses',
            'loyalty_accounts',
            'loyalty_transactions',
            'product_categories',
            'products',
            'assets',
            'branch_inventories',
            'asset_status_histories',
            'rate_plans',
            'product_rates',
            'packages',
            'package_items',
            'package_rates',
            'promotions',
            'bookings',
            'booking_items',
            'asset_reservations',
            'booking_status_histories',
            'rentals',
            'rental_items',
            'rental_item_assets',
            'rental_extensions',
            'rental_extension_items',
            'rental_returns',
            'rental_return_items',
            'rental_collaterals',
            'asset_inspections',
            'asset_inspection_media',
            'damage_charges',
            'maintenance_orders',
            'rental_status_histories',
            'payment_methods',
            'financial_categories',
            'cash_registers',
            'cash_sessions',
            'payments',
            'payment_allocations',
            'refunds',
            'cash_transactions',
            'branch_transfers',
            'branch_transfer_items',
            'activity_logs',
            'status_histories',
            'legacy_import_batches',
            'legacy_import_tables',
            'legacy_import_rows',
            'legacy_import_mappings',
            'legacy_id_maps',
            'legacy_import_issues',
            'legacy_import_events',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('users', [
            'company_id',
            'current_branch_id',
            'status',
            'last_login_at',
            'last_logout_at',
        ]));

        $this->assertTrue(Schema::hasColumns('rentals', [
            'branch_id',
            'customer_id',
            'legacy_number',
            'status',
            'balance_due',
        ]));

        $this->assertTrue(Schema::hasColumns('legacy_import_batches', [
            'branch_id',
            'source_path',
            'source_sha256',
            'status',
            'previewed_at',
            'validated_at',
            'mapped_at',
            'executed_at',
            'verified_at',
        ]));
    }

    public function test_foundation_seeder_is_idempotent(): void
    {
        $this->seed(RentalFoundationSeeder::class);
        $this->seed(RentalFoundationSeeder::class);

        $this->assertDatabaseCount('companies', 1);
        $this->assertDatabaseCount('branches', 1);
        $this->assertDatabaseHas('companies', [
            'code' => 'TK',
            'name' => 'Together Kamera',
        ]);
        $this->assertDatabaseHas('branches', [
            'code' => 'PNG',
            'name' => 'Together Kamera Ponorogo',
        ]);
        $this->assertDatabaseCount('roles', 6);
        $this->assertDatabaseCount('rate_plans', 3);
        $this->assertDatabaseCount('payment_methods', 4);
        $this->assertGreaterThanOrEqual(40, DB::table('permissions')->count());
    }

    public function test_existing_user_can_be_bootstrapped_as_super_administrator(): void
    {
        $this->seed(RentalFoundationSeeder::class);

        $user = User::factory()->create([
            'email' => 'admin@togetherkamera.test',
            'email_verified_at' => null,
        ]);

        $this->artisan('rental:bootstrap-admin', [
            'email' => $user->email,
            '--company' => 'TK',
            '--branch' => 'PNG',
        ])->assertSuccessful();

        $user->refresh();
        $branchId = (int) DB::table('branches')->where('code', 'PNG')->value('id');
        $superAdminRoleId = (int) DB::table('roles')->where('slug', 'super-admin')->value('id');

        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($branchId, $user->current_branch_id);
        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('role_user', [
            'role_id' => $superAdminRoleId,
            'user_id' => $user->id,
            'branch_id' => null,
        ]);
    }

    public function test_bootstrap_administrator_rejects_the_documentation_placeholder(): void
    {
        $this->artisan('rental:bootstrap-admin', [
            'email' => 'EMAIL_LOGIN_ANDA',
        ])->assertFailed();
    }
}
