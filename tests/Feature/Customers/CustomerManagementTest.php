<?php

namespace Tests\Feature\Customers;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerIdentity;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_administrator_can_open_customer_list_and_customer_360(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $customer = $this->customer($user, $branch);

        $this->actingAs($user)
            ->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('customers/index')
                ->where('summary.total', 1)
                ->where('customers.data.0.id', $customer->id)
                ->where('permissions.create', true));

        $this->actingAs($user)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('customers/show')
                ->where('customer.id', $customer->id)
                ->where('statistics.rentals', 0)
                ->where('permissions.loyalty', true));
    }

    public function test_customer_number_and_member_number_are_generated_atomically(): void
    {
        [$user, $branch] = $this->superAdministrator();

        $this->actingAs($user)
            ->post(route('customers.store'), $this->customerPayload($branch, [
                'name' => 'Member Baru',
                'is_member' => true,
                'member_number' => null,
                'member_since' => null,
            ]))
            ->assertSessionHasNoErrors();

        $customer = Customer::query()->where('name', 'Member Baru')->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^PNG-CUS-\d{4}-\d{6}$/',
            $customer->customer_number,
        );
        $this->assertSame("MBR-{$customer->customer_number}", $customer->member_number);
        $this->assertSame($branch->id, $customer->registered_branch_id);
        $this->assertDatabaseHas('number_sequences', [
            'branch_id' => $branch->id,
            'document_type' => 'customer',
            'month' => 0,
            'last_number' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'subject_id' => $customer->id,
            'event' => 'customer.created',
        ]);
    }

    public function test_branch_scoped_user_cannot_register_customer_in_another_branch(): void
    {
        [$administrator, $branch] = $this->superAdministrator();
        $otherBranch = Branch::query()->create([
            'company_id' => $administrator->company_id,
            'code' => 'MDO',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $operator = $this->branchOperator(
            (int) $administrator->company_id,
            $branch,
        );

        $this->actingAs($operator)
            ->post(route('customers.store'), $this->customerPayload($otherBranch))
            ->assertSessionHasErrors('registered_branch_id');

        $this->assertDatabaseMissing('customers', [
            'company_id' => $administrator->company_id,
            'name' => 'Pelanggan Baru',
        ]);
    }

    public function test_risk_profile_requires_customer_verification_permission(): void
    {
        [$administrator, $branch] = $this->superAdministrator();
        $customer = $this->customer($administrator, $branch);
        $operator = $this->branchOperator(
            (int) $administrator->company_id,
            $branch,
            withVerification: false,
        );

        $this->actingAs($operator)
            ->put(
                route('customers.update', $customer),
                $this->customerPayload($branch, [
                    'name' => $customer->name,
                    'risk_level' => 'high',
                ]),
            )
            ->assertSessionHasErrors('risk_level');

        $this->assertSame('normal', $customer->fresh()->risk_level);
    }

    public function test_identity_can_be_added_and_verified_but_cannot_be_duplicated(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $customer = $this->customer($user, $branch);

        $this->actingAs($user)
            ->post(route('customers.identities.store', $customer), [
                'type' => 'ktp',
                'number' => '3502010101010001',
                'name_on_identity' => $customer->name,
                'expires_at' => null,
                'is_primary' => false,
            ])
            ->assertSessionHasNoErrors();

        $identity = CustomerIdentity::query()->firstOrFail();
        $this->assertTrue($identity->is_primary);

        $otherCustomer = $this->customer($user, $branch, [
            'customer_number' => 'LEG-PNG-2',
            'name' => 'Pelanggan Kedua',
        ]);

        $this->actingAs($user)
            ->post(route('customers.identities.store', $otherCustomer), [
                'type' => 'ktp',
                'number' => '3502010101010001',
                'name_on_identity' => $otherCustomer->name,
                'expires_at' => null,
                'is_primary' => true,
            ])
            ->assertSessionHasErrors('number');

        $this->actingAs($user)
            ->patch(route('customer-identities.verify', $identity))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($identity->fresh()->verified_at);
        $this->assertSame($user->id, $identity->fresh()->verified_by);
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $user->id,
            'subject_id' => $identity->id,
            'event' => 'customer.identity_verified',
        ]);

        $this->actingAs($user)
            ->put(route('customer-identities.update', $identity), [
                'type' => 'ktp',
                'number' => '3502010101010002',
                'name_on_identity' => $customer->name,
                'expires_at' => null,
                'is_primary' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($identity->fresh()->verified_at);
        $this->assertNull($identity->fresh()->verified_by);
    }

    public function test_new_primary_address_replaces_the_previous_primary(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $customer = $this->customer($user, $branch);

        $first = CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'type' => 'identity',
            'address' => 'Jalan Lama',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->post(route('customers.addresses.store', $customer), [
                'type' => 'domicile',
                'address' => 'Jalan Baru',
                'village' => 'Mangkujayan',
                'district' => 'Ponorogo',
                'city' => 'Ponorogo',
                'province' => 'Jawa Timur',
                'postal_code' => '63413',
                'is_primary' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $customer->id,
            'address' => 'Jalan Baru',
            'is_primary' => true,
        ]);
    }

    public function test_loyalty_earn_redeem_and_tier_are_consistent(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $customer = $this->customer($user, $branch);

        $this->actingAs($user)
            ->post(route('customers.loyalty.store', $customer), [
                'type' => 'earn',
                'points' => 1200,
                'description' => 'Bonus member awal',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('customers.loyalty.store', $customer), [
                'type' => 'redeem',
                'points' => 200,
                'description' => 'Potongan transaksi',
            ])
            ->assertSessionHasNoErrors();

        $account = $customer->loyaltyAccount()->firstOrFail();
        $this->assertSame(1000, $account->points_balance);
        $this->assertSame(1200, $account->lifetime_points);
        $this->assertSame('silver', $account->tier);
        $this->assertSame(2, $account->transactions()->count());

        $this->actingAs($user)
            ->post(route('customers.loyalty.store', $customer), [
                'type' => 'redeem',
                'points' => 2000,
                'description' => 'Melebihi saldo',
            ])
            ->assertSessionHasErrors('points');

        $this->assertSame(1000, $account->fresh()->points_balance);
        $this->assertSame(2, $account->transactions()->count());
    }

    public function test_customer_with_active_rental_cannot_be_archived(): void
    {
        [$user, $branch] = $this->superAdministrator();
        $customer = $this->customer($user, $branch);
        $rental = Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => 'PNG-RNT-TEST-001',
            'status' => 'checked_out',
            'checked_out_at' => now(),
            'due_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->delete(route('customers.archive', $customer))
            ->assertSessionHasErrors('customer');

        $this->assertNull($customer->fresh()->deleted_at);

        $rental->update(['status' => 'returned', 'returned_at' => now()]);

        $this->actingAs($user)
            ->delete(route('customers.archive', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_user_without_customer_permission_cannot_open_the_module(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('customers.index'))
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
            'status' => 'active',
        ]);
        $roleId = (int) DB::table('roles')
            ->where('company_id', $companyId)
            ->where('slug', 'super-admin')
            ->value('id');
        $this->assignAccess($user, $branch, $roleId, null);

        return [$user, $branch];
    }

    private function branchOperator(
        int $companyId,
        Branch $branch,
        bool $withVerification = true,
    ): User {
        $slugs = [
            'branches.switch',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.loyalty',
        ];

        if ($withVerification) {
            $slugs[] = 'customers.verify';
        }

        $role = Role::query()->create([
            'company_id' => $companyId,
            'name' => 'Customer Operator '.($withVerification ? 'Verified' : 'Basic'),
            'slug' => 'customer-operator-'.($withVerification ? 'verified' : 'basic'),
            'scope' => 'branch',
            'is_system' => false,
        ]);
        $role->permissions()->sync(
            DB::table('permissions')->whereIn('slug', $slugs)->pluck('id'),
        );
        $user = User::factory()->create([
            'company_id' => $companyId,
            'current_branch_id' => $branch->id,
            'status' => 'active',
        ]);
        $this->assignAccess($user, $branch, $role->id, $branch->id);

        return $user;
    }

    private function assignAccess(
        User $user,
        Branch $branch,
        int $roleId,
        ?int $roleBranchId,
    ): void {
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
            'branch_id' => $roleBranchId,
            'assigned_by' => null,
            'assigned_at' => $now,
            'expires_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function customer(
        User $user,
        Branch $branch,
        array $overrides = [],
    ): Customer {
        return Customer::query()->create([
            'company_id' => $user->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'LEG-PNG-1',
            'name' => 'Pelanggan Legacy',
            'is_member' => false,
            'status' => 'active',
            'risk_level' => 'normal',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function customerPayload(Branch $branch, array $overrides = []): array
    {
        return [
            'registered_branch_id' => $branch->id,
            'name' => 'Pelanggan Baru',
            'gender' => 'male',
            'phone' => '081234567890',
            'email' => 'pelanggan@example.test',
            'birth_place' => 'Ponorogo',
            'birth_date' => '2000-01-01',
            'institution' => 'AnzArt Studio',
            'is_member' => false,
            'member_number' => null,
            'member_since' => null,
            'status' => 'active',
            'risk_level' => 'normal',
            'notes' => 'Pelanggan pengujian.',
            ...$overrides,
        ];
    }
}
