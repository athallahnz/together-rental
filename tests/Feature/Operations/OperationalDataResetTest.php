<?php

namespace Tests\Feature\Operations;

use App\Domain\Branches\BranchProvisioner;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OperationalDataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_open_reset_center_and_preview_current_branch(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $this->seedOperationalData($user, $branch);

        $this->actingAs($user)
            ->get(route('operations.reset.index', ['scope' => $branch->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('operations/reset')
                ->where('selectedScope', (string) $branch->id)
                ->where('confirmationPhrase', 'RESET PNG')
                ->where('summary.bookings', 1)
                ->where('summary.rentals', 1)
                ->where('summary.maintenance', 1)
                ->where('summary.transfers', 1)
                ->where('summary.serialized_assets', 1));
    }

    public function test_branch_reset_clears_operational_transactions_and_restores_inventory_without_deleting_master_data(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $other = $this->secondBranch($user);
        $fixture = $this->seedOperationalData($user, $branch, $other);
        $this->seedOtherBranchBooking($user, $other, $fixture['customer'], $fixture['product']);

        $this->actingAs($user)
            ->post(route('operations.reset.store'), [
                'scope' => (string) $branch->id,
                'confirmation_phrase' => 'RESET PNG',
                'password' => 'password',
                'normalize_condition' => true,
            ])
            ->assertRedirect(route('operations.reset.index', ['scope' => $branch->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('bookings', ['branch_id' => $branch->id]);
        $this->assertDatabaseMissing('rentals', ['branch_id' => $branch->id]);
        $this->assertDatabaseMissing('maintenance_orders', ['branch_id' => $branch->id]);
        $this->assertDatabaseMissing('branch_transfers', ['id' => $fixture['transfer_id']]);
        $this->assertDatabaseMissing('payments', ['id' => $fixture['payment_id']]);
        $this->assertDatabaseMissing('rental_financial_adjustments', ['rental_id' => $fixture['rental_id']]);

        $this->assertDatabaseHas('bookings', ['branch_id' => $other->id]);
        $this->assertDatabaseHas('customers', ['id' => $fixture['customer']->id]);
        $this->assertDatabaseHas('products', ['id' => $fixture['product']->id]);
        $this->assertDatabaseHas('assets', [
            'id' => $fixture['asset']->id,
            'current_branch_id' => $branch->id,
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('branch_inventories', [
            'branch_id' => $branch->id,
            'product_id' => $fixture['product']->id,
            'quantity_on_hand' => 12,
            'quantity_reserved' => 0,
            'quantity_rented' => 0,
            'quantity_maintenance' => 0,
            'quantity_in_transfer' => 0,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'branch_id' => $branch->id,
            'event' => 'operations.reset',
        ]);
    }

    public function test_reset_can_keep_asset_condition_while_making_asset_available(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $fixture = $this->seedOperationalData($user, $branch);

        $this->actingAs($user)
            ->post(route('operations.reset.store'), [
                'scope' => (string) $branch->id,
                'confirmation_phrase' => 'RESET PNG',
                'password' => 'password',
                'normalize_condition' => false,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assets', [
            'id' => $fixture['asset']->id,
            'status' => 'available',
            'condition' => 'damaged',
        ]);
    }

    public function test_reset_requires_exact_confirmation_and_current_password(): void
    {
        [$user, $branch] = $this->superAdministrator();

        $this->actingAs($user)
            ->post(route('operations.reset.store'), [
                'scope' => (string) $branch->id,
                'confirmation_phrase' => 'RESET SALAH',
                'password' => 'wrong-password',
                'normalize_condition' => false,
            ])
            ->assertSessionHasErrors(['confirmation_phrase', 'password']);
    }

    public function test_company_user_who_is_not_super_administrator_cannot_open_reset_center(): void
    {
        [$administrator, $branch] = $this->superAdministrator();
        $user = User::factory()->create([
            'company_id' => $administrator->company_id,
            'current_branch_id' => $branch->id,
        ]);
        $roleId = (int) DB::table('roles')
            ->where('company_id', $administrator->company_id)
            ->where('slug', 'owner-management')
            ->value('id');

        DB::table('role_user')->insert([
            'role_id' => $roleId,
            'user_id' => $user->id,
            'branch_id' => null,
            'assigned_by' => $administrator->id,
            'assigned_at' => now(),
            'expires_at' => null,
        ]);

        $this->actingAs($user)
            ->get(route('operations.reset.index'))
            ->assertForbidden();
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

    private function secondBranch(User $administrator): Branch
    {
        $branch = Branch::query()->create([
            'company_id' => $administrator->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        app(BranchProvisioner::class)->provision($branch);

        return $branch;
    }

    /**
     * @return array{customer: Customer, product: Product, asset: Asset, rental_id: int, payment_id: int, transfer_id: int}
     */
    private function seedOperationalData(User $user, Branch $branch, ?Branch $other = null): array
    {
        $other ??= $this->secondBranch($user);
        $customer = Customer::query()->create([
            'company_id' => $user->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'UAT-CUST-001',
            'name' => 'Pelanggan UAT',
            'status' => 'active',
            'risk_level' => 'normal',
            'is_member' => false,
        ]);
        $product = Product::query()->create([
            'company_id' => $user->company_id,
            'sku' => 'UAT-CAM-001',
            'name' => 'Kamera UAT',
            'tracking_type' => 'serialized',
            'replacement_value' => 10000000,
            'is_rentable' => true,
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'PNG-UAT-001',
            'serial_number' => 'UAT-SERIAL-001',
            'status' => 'rented',
            'condition' => 'damaged',
            'is_active' => true,
        ]);
        DB::table('branch_inventories')->insert([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 12,
            'quantity_reserved' => 2,
            'quantity_rented' => 3,
            'quantity_maintenance' => 1,
            'quantity_in_transfer' => 2,
            'reorder_level' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bookingId = DB::table('bookings')->insertGetId([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'BKG-PNG-UAT-0001',
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'deposit_required' => 0,
            'deposit_paid' => 0,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bookingItemId = DB::table('booking_items')->insertGetId([
            'booking_id' => $bookingId,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_rate' => 100000,
            'total_amount' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('asset_reservations')->insert([
            'branch_id' => $branch->id,
            'booking_id' => $bookingId,
            'booking_item_id' => $bookingItemId,
            'asset_id' => $asset->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'status' => 'reserved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rentalId = DB::table('rentals')->insertGetId([
            'branch_id' => $branch->id,
            'booking_id' => $bookingId,
            'customer_id' => $customer->id,
            'rental_number' => 'RNT-PNG-UAT-0001',
            'status' => 'active',
            'checked_out_at' => now()->subDay(),
            'due_at' => now()->addDay(),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'paid_amount' => 50000,
            'balance_due' => 50000,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rentalItemId = DB::table('rental_items')->insertGetId([
            'rental_id' => $rentalId,
            'booking_item_id' => $bookingItemId,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_rate' => 100000,
            'total_amount' => 100000,
            'status' => 'out',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rental_item_assets')->insert([
            'rental_item_id' => $rentalItemId,
            'asset_id' => $asset->id,
            'checkout_condition' => 'good',
            'checked_out_at' => now()->subDay(),
            'status' => 'out',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $returnId = DB::table('rental_returns')->insertGetId([
            'branch_id' => $branch->id,
            'rental_id' => $rentalId,
            'return_number' => 'RTN-PNG-UAT-0001',
            'type' => 'partial',
            'status' => 'completed',
            'returned_at' => now(),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $returnItemId = DB::table('rental_return_items')->insertGetId([
            'rental_return_id' => $returnId,
            'rental_item_id' => $rentalItemId,
            'asset_id' => $asset->id,
            'quantity' => 1,
            'condition' => 'damaged',
            'status' => 'returned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('asset_inspections')->insert([
            'branch_id' => $branch->id,
            'asset_id' => $asset->id,
            'rental_item_id' => $rentalItemId,
            'rental_return_item_id' => $returnItemId,
            'type' => 'return',
            'condition' => 'damaged',
            'inspected_by' => $user->id,
            'inspected_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('maintenance_orders')->insert([
            'branch_id' => $branch->id,
            'asset_id' => $asset->id,
            'maintenance_number' => 'MNT-PNG-UAT-0001',
            'type' => 'corrective',
            'status' => 'reported',
            'problem_description' => 'Data UAT',
            'reported_at' => now(),
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentMethodId = (int) DB::table('payment_methods')->where('code', 'CASH')->value('id');
        $paymentId = DB::table('payments')->insertGetId([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_id' => $bookingId,
            'rental_id' => $rentalId,
            'payment_method_id' => $paymentMethodId,
            'payment_number' => 'PAY-PNG-UAT-0001',
            'direction' => 'in',
            'type' => 'rental',
            'status' => 'completed',
            'amount' => 50000,
            'paid_at' => now(),
            'received_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('rental_financial_adjustments')->insert([
            'rental_id' => $rentalId,
            'branch_id' => $branch->id,
            'adjustment_number' => 'ADJ-PNG-UAT-0001',
            'component' => 'late_fee',
            'direction' => 'increase',
            'amount' => 10000,
            'balance_before' => 50000,
            'balance_after' => 60000,
            'reason' => 'Data UAT',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('branch_transfers')->insertGetId([
            'company_id' => $user->company_id,
            'from_branch_id' => $branch->id,
            'to_branch_id' => $other->id,
            'transfer_number' => 'TRF-PNG-UAT-0001',
            'status' => 'approved',
            'revision_number' => 1,
            'lock_version' => 0,
            'reason' => 'Data UAT',
            'requested_by' => $user->id,
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branch_transfer_items')->insert([
            'branch_transfer_id' => $transferId,
            'line_number' => 1,
            'product_id' => $product->id,
            'asset_id' => $asset->id,
            'previous_branch_id' => $branch->id,
            'previous_asset_status' => 'available',
            'quantity' => 1,
            'received_quantity' => 0,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'customer' => $customer,
            'product' => $product,
            'asset' => $asset,
            'rental_id' => $rentalId,
            'payment_id' => $paymentId,
            'transfer_id' => $transferId,
        ];
    }

    private function seedOtherBranchBooking(
        User $user,
        Branch $branch,
        Customer $customer,
        Product $product,
    ): void {
        DB::table('bookings')->insert([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => 'BKG-MDN-UAT-0001',
            'status' => 'draft',
            'source' => 'counter',
            'booked_at' => now(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(6),
            'subtotal' => 100000,
            'total_amount' => 100000,
            'deposit_required' => 0,
            'deposit_paid' => 0,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
