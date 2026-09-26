<?php

namespace Tests\Feature\Rentals;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class RentalCheckoutTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_confirmed_booking_is_checked_out_atomically(): void
    {
        [$user, $branch, $customer, $plan, $product, $asset] = $this->fixture();
        $booking = $this->createConfirmedBooking(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
        );
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'assets' => [[
                'asset_id' => $asset->id,
                'condition' => 'excellent',
                'notes' => 'Baterai dan charger lengkap.',
            ]],
            'payment_amount' => 100000,
            'deposit_paid' => 500000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect();

        $rental = Rental::query()->firstOrFail();

        $this->assertSame('active', $rental->status);
        $this->assertSame('converted', $booking->fresh()->status);
        $this->assertSame('rented', $asset->fresh()->status);
        $this->assertSame('100000.00', $rental->paid_amount);
        $this->assertSame('50000.00', $rental->balance_due);
        $this->assertDatabaseHas('asset_reservations', [
            'booking_id' => $booking->id,
            'asset_id' => $asset->id,
            'status' => 'converted',
        ]);
        $this->assertDatabaseHas('rental_item_assets', [
            'asset_id' => $asset->id,
            'checkout_condition' => 'excellent',
            'status' => 'out',
        ]);
        $this->assertDatabaseHas('asset_inspections', [
            'asset_id' => $asset->id,
            'type' => 'checkout',
            'condition' => 'excellent',
        ]);
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $rental->id,
            'event' => 'rental.booking_checked_out',
        ]);
    }

    public function test_direct_rental_creates_internal_booking_and_checks_out(): void
    {
        [$user, $branch, $customer, $plan, $product, $asset] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->bookingPayload($branch, $customer, $plan, $product),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 50000,
            'deposit_paid' => 0,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
            'collaterals' => [[
                'type' => 'KTP',
                'number' => 'DIRECT-REQUIRED-001',
                'holder_name' => 'Pelanggan Direct',
            ]],
        ])->assertSessionHasNoErrors()
            ->assertRedirect();

        $booking = Booking::query()->firstOrFail();
        $rental = Rental::query()->firstOrFail();

        $this->assertSame('direct', $booking->source);
        $this->assertSame('converted', $booking->status);
        $this->assertSame($booking->id, $rental->booking_id);
        $this->assertSame('active', $rental->status);
        $this->assertSame('50000.00', $rental->paid_amount);
        $this->assertSame('rented', $asset->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'source_context' => 'rental_checkout',
            'cash_session_id' => $session->id,
            'amount' => 50000,
        ]);
        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('rental_status_histories', [
            'rental_id' => $rental->id,
            'to_status' => 'active',
        ]);
        $this->assertDatabaseHas('rental_collaterals', [
            'rental_id' => $rental->id,
            'type' => 'KTP',
            'number' => 'DIRECT-REQUIRED-001',
            'status' => 'held',
        ]);
    }

    public function test_direct_rental_copies_member_pricing_snapshot_from_internal_booking(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $customer->update([
            'is_member' => true,
            'member_number' => 'MBR-DIRECT',
            'member_since' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->bookingPayload($branch, $customer, $plan, $product),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 0,
            'deposit_paid' => 0,
            'collaterals' => [[
                'type' => 'KTP',
                'number' => 'DIRECT-MEMBER-001',
                'holder_name' => 'Member Direct',
            ]],
        ])->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();
        $rental = Rental::query()->firstOrFail();
        $this->assertSame('150000.00', $booking->subtotal);
        $this->assertSame('15000.00', $booking->discount_amount);
        $this->assertSame('135000.00', $booking->total_amount);
        $this->assertSame('135000.00', $rental->total_amount);
        $this->assertSame($booking->pricing_snapshot, $rental->pricing_snapshot);
        $this->assertSame('membership', $rental->pricing_snapshot['discount_strategy']);
    }

    public function test_direct_rental_requires_physical_collateral_and_cannot_bypass_via_post(): void
    {
        [$user, $branch, $customer, $plan, $product, $asset] = $this->fixture();

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->bookingPayload($branch, $customer, $plan, $product),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 0,
            'deposit_paid' => 0,
            'collaterals' => [],
        ])->assertSessionHasErrors([
            'collaterals' => 'Minimal satu jaminan fisik/dokumen wajib diterima untuk Rental In Store.',
        ]);

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseCount('rental_collaterals', 0);
        $this->assertSame('available', $asset->fresh()->status);
    }

    public function test_direct_rental_customer360_lookup_prefers_eligible_verified_identity_and_never_exposes_file_path(): void
    {
        [$user, $branch, $customer] = $this->fixture();
        $expired = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'EXPIRED-DIRECT-KTP',
            'document_path' => 'private/never-expose-original-file.pdf',
            'expires_at' => now()->subDay()->toDateString(),
            'is_primary' => true,
            'verified_at' => now()->subMonth(),
            'verified_by' => $user->id,
        ]);
        $eligible = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'sim',
            'number' => 'SIM-DIRECT-VALID',
            'name_on_identity' => 'Canonical Owner',
            'expires_at' => now()->addYear()->toDateString(),
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        $this->actingAs($user)->getJson(route('rentals.direct.customer-identities', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]))->assertOk()
            ->assertJsonPath('data.0.id', $expired->id)
            ->assertJsonPath('data.0.is_expired', true)
            ->assertJsonPath('data.0.is_default', false)
            ->assertJsonPath('data.1.id', $eligible->id)
            ->assertJsonPath('data.1.collateral_type', 'SIM')
            ->assertJsonPath('data.1.is_default', true)
            ->assertJsonPath('data.0.document_present', true)
            ->assertDontSee('private/never-expose-original-file.pdf');

        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseCount('rental_collaterals', 0);
    }

    public function test_direct_rental_customer360_lookup_reflects_identities_added_after_initial_lookup(): void
    {
        [$user, $branch, $customer] = $this->fixture();
        $url = route('rentals.direct.customer-identities', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'KTP-ADDED-AFTER-LOOKUP',
            'is_primary' => true,
        ]);

        $this->actingAs($user)->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $identity->id)
            ->assertJsonPath('data.0.is_default', true);
    }

    public function test_direct_rental_customer360_lookup_requires_rental_create_permission(): void
    {
        [$user, $branch, $customer] = $this->fixture();
        $unprivileged = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $unprivileged->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($unprivileged)->getJson(route('rentals.direct.customer-identities', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]))->assertForbidden();
    }

    public function test_direct_rental_customer360_lookup_is_branch_and_company_scoped(): void
    {
        [$user, $branch] = $this->fixture();
        $otherCompany = Company::query()->create([
            'code' => 'OTHER',
            'name' => 'Other Company',
        ]);
        $otherCustomer = Customer::query()->create([
            'company_id' => $otherCompany->id,
            'customer_number' => 'OTHER-CUS-0001',
            'name' => 'Confidential Customer',
            'status' => 'active',
        ]);

        $this->actingAs($user)->getJson(route('rentals.direct.customer-identities', [
            'customer_id' => $otherCustomer->id,
            'branch_id' => $branch->id,
        ]))->assertNotFound();
        $this->actingAs($user)->getJson(route('rentals.direct.customer-identities', [
            'customer_id' => $otherCustomer->id,
            'branch_id' => 999999,
        ]))->assertNotFound();
        $this->assertDatabaseCount('rental_collaterals', 0);
    }

    public function test_direct_rental_customer360_lookup_blocks_other_accessible_company_branches(): void
    {
        [, $branch, $customer] = $this->fixture();
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDO',
            'name' => 'Other Accessible Company Branch',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $operator = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'rental-operator')->firstOrFail();
        $operator->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $operator->roles()->attach($role->id, [
            'branch_id' => $branch->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($operator)->getJson(route('rentals.direct.customer-identities', [
            'customer_id' => $customer->id,
            'branch_id' => $other->id,
        ]))->assertNotFound();
        $this->actingAs($operator)->getJson(route('rentals.direct.customer-identities', [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
        ]))->assertOk();
    }

    public function test_direct_rental_customer360_identity_requires_physical_receipt_confirmation(): void
    {
        [$user, $branch, $customer, $plan, $product, $asset] = $this->fixture();
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'DIRECT-LINKED-KTP',
            'name_on_identity' => 'Canonical Holder',
            'is_primary' => true,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);
        $payload = [
            ...$this->bookingPayload($branch, $customer, $plan, $product),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'deposit_paid' => 0,
            'collaterals' => [[
                'customer_identity_id' => $identity->id,
                'type' => 'SIM',
                'number' => 'CLIENT-EDITED',
                'holder_name' => 'Spoofed Name',
            ]],
        ];

        $this->actingAs($user)->post(route('rentals.direct.store'), $payload)
            ->assertSessionHasErrors('customer360_received_confirmed');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame('available', $asset->fresh()->status);

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$payload,
            'customer360_received_confirmed' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rental = Rental::query()->firstOrFail();
        $collateral = $rental->collaterals()->firstOrFail();
        $this->assertSame('active', $rental->status);
        $this->assertSame('0.00', $rental->deposit_amount);
        $this->assertSame('customer_identity', $collateral->source_type);
        $this->assertSame($identity->id, $collateral->customer_identity_id);
        $this->assertSame('KTP', $collateral->type);
        $this->assertSame('DIRECT-LINKED-KTP', $collateral->number);
        $this->assertSame('Canonical Holder', $collateral->holder_name);
        $this->assertSame($identity->id, $collateral->identity_snapshot['customer_identity_id']);
    }

    public function test_direct_rental_rejects_customer360_identity_from_another_customer(): void
    {
        [$user, $branch, $customer, $plan, $product, $asset] = $this->fixture();
        $other = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-DIRECT-OTHER',
            'name' => 'Different Customer',
            'status' => 'active',
        ]);
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $other->id,
            'type' => 'ktp',
            'number' => 'OTHER-DIRECT-IDENTITY',
        ]);
        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->bookingPayload($branch, $customer, $plan, $product),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'customer360_received_confirmed' => true,
            'collaterals' => [[
                'customer_identity_id' => $identity->id,
                'type' => 'KTP',
                'number' => 'OTHER-DIRECT-IDENTITY',
            ]],
        ])->assertSessionHasErrors('collaterals');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseCount('rental_collaterals', 0);
        $this->assertSame('available', $asset->fresh()->status);
    }

    public function test_booking_payments_are_carried_into_rental_balance(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);
        $payload = $this->bookingPayload($branch, $customer, $plan, $product);
        $payload['payment_amount'] = 50000;
        $payload['deposit_paid'] = 200000;
        $payload['payment_method_id'] = $method->id;
        $payload['cash_session_id'] = $session->id;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 25000,
            'deposit_paid' => 300000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
        ])->assertSessionHasNoErrors()
            ->assertRedirect();

        $rental = Rental::query()->firstOrFail();

        $this->assertSame('50000.00', $rental->booking_payment_amount);
        $this->assertSame('75000.00', $rental->paid_amount);
        $this->assertSame('75000.00', $rental->balance_due);
        $this->assertSame('500000.00', $rental->deposit_amount);
        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id,
            'rental_id' => null,
            'status' => 'completed',
        ]);
        $this->assertDatabaseCount('cash_transactions', 4);
        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'source_context' => 'rental_checkout',
            'type' => 'deposit',
            'amount' => 300000,
        ]);
    }

    public function test_draft_or_already_converted_booking_cannot_be_checked_out(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $payload = $this->bookingPayload($branch, $customer, $plan, $product);
        $this->actingAs($user)->post(route('bookings.store'), $payload);
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('rentals', 0);
        $this->assertSame('available', Asset::query()->firstOrFail()->status);
    }

    public function test_operator_cannot_checkout_booking_from_another_branch(): void
    {
        [$administrator, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->createConfirmedBooking(
            $administrator,
            $branch,
            $customer,
            $plan,
            $product,
        );
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDO',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $operator = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $other->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'rental-operator')->firstOrFail();
        $operator->branches()->attach($other->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $operator->roles()->attach($role->id, [
            'branch_id' => $other->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($operator)
            ->get(route('rentals.checkout.create', $booking))
            ->assertNotFound();
    }

    /** @return array{User, Branch, Customer, RatePlan, Product, Asset} */
    private function fixture(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'super-admin')->firstOrFail();
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach($role->id, [
            'branch_id' => null,
            'assigned_at' => now(),
        ]);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-RENTAL',
            'name' => 'Pelanggan Rental',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-RENTAL',
            'name' => 'Kamera Rental',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        ProductRate::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $plan->id,
            'amount' => 150000,
            'deposit_amount' => 500000,
            'is_active' => true,
        ]);
        $asset = Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'CAM-RENTAL-001',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);

        return [$user, $branch, $customer, $plan, $product, $asset];
    }

    private function createConfirmedBooking(
        User $user,
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
    ): Booking {
        $this->actingAs($user)->post(
            route('bookings.store'),
            $this->bookingPayload($branch, $customer, $plan, $product),
        )->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        return $booking->fresh();
    }

    /** @return array<string, mixed> */
    private function bookingPayload(
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
    ): array {
        return [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'source' => 'counter',
            'starts_at' => now()->addMinutes(5)->format('Y-m-d H:i:s'),
            'duration_units' => 1,
            'items' => [[
                'type' => 'product',
                'id' => $product->id,
                'quantity' => 1,
            ]],
        ];
    }
}
