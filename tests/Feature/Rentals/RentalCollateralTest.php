<?php

namespace Tests\Feature\Rentals;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class RentalCollateralTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_direct_rental_records_physical_collateral_separately_from_cash_deposit(): void
    {
        Storage::fake('local');
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->rentalPayload($branch, $customer, $plan, $product),
            'payment_amount' => 50000,
            'deposit_paid' => 100000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
            'collaterals' => [[
                'type' => 'KTP',
                'number' => '3502010101010001',
                'holder_name' => 'Pelanggan Collateral',
                'notes' => 'Disimpan di laci kasir.',
                'document' => UploadedFile::fake()->image('ktp.jpg'),
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rental = Rental::query()->firstOrFail();
        $collateral = RentalCollateral::query()->firstOrFail();

        $this->assertSame('100000.00', $rental->deposit_amount);
        $this->assertSame('held', $collateral->status);
        $this->assertSame($rental->id, $collateral->rental_id);
        $this->assertSame($customer->id, $collateral->customer_id);
        $this->assertSame($user->id, $collateral->received_by);
        $this->assertNotNull($collateral->received_at);
        $this->assertNotNull($collateral->document_path);
        Storage::disk('local')->assertExists($collateral->document_path);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_received',
            'subject_id' => $collateral->id,
        ]);
    }

    public function test_confirmed_booking_checkout_can_receive_multiple_collaterals(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $payload = $this->bookingPayload($branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $booking = Booking::query()->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'collaterals' => [
                [
                    'type' => 'SIM',
                    'number' => 'SIM-0001',
                    'holder_name' => $customer->name,
                ],
                [
                    'type' => 'Kartu Mahasiswa',
                    'number' => 'MHS-0001',
                    'holder_name' => $customer->name,
                ],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rental = Rental::query()->firstOrFail();
        $this->assertDatabaseCount('rental_collaterals', 2);
        $this->assertSame(2, $rental->collaterals()->where('status', 'held')->count());
        $this->assertDatabaseHas('rental_collaterals', [
            'rental_id' => $rental->id,
            'type' => 'SIM',
            'number' => 'SIM-0001',
            'status' => 'held',
        ]);
    }

    public function test_checkout_exposes_customer360_identity_and_marks_primary_verified_identity_as_default(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => '3502010101010099',
            'name_on_identity' => 'Pelanggan Customer360',
            'is_primary' => true,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);
        $booking = $this->confirmedBooking($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)
            ->get(route('rentals.checkout.create', $booking))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rentals/checkout')
                ->where('customerIdentities.0.id', $identity->id)
                ->where('customerIdentities.0.collateral_type', 'KTP')
                ->where('customerIdentities.0.is_default', true)
                ->where('customerIdentities.0.is_expired', false));
    }

    public function test_checkout_skips_expired_primary_identity_when_choosing_customer360_default(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $expiredPrimary = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'EXPIRED-PRIMARY',
            'expires_at' => now()->subDay()->toDateString(),
            'is_primary' => true,
            'verified_at' => now()->subMonth(),
            'verified_by' => $user->id,
        ]);
        $verifiedSecondary = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'sim',
            'number' => 'VALID-SECONDARY',
            'expires_at' => now()->addYear()->toDateString(),
            'is_primary' => false,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);
        $booking = $this->confirmedBooking($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)
            ->get(route('rentals.checkout.create', $booking))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('customerIdentities.0.id', $expiredPrimary->id)
                ->where('customerIdentities.0.is_expired', true)
                ->where('customerIdentities.0.is_default', false)
                ->where('customerIdentities.1.id', $verifiedSecondary->id)
                ->where('customerIdentities.1.is_default', true));
    }

    public function test_customer360_collateral_is_resolved_server_side_and_does_not_pay_cash_deposit(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'sim',
            'number' => 'SIM-C360-001',
            'name_on_identity' => 'Nama Canonical',
            'expires_at' => now()->addYear()->toDateString(),
            'is_primary' => true,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);
        $booking = $this->confirmedBooking($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'deposit_paid' => 0,
            'collaterals' => [[
                'customer_identity_id' => $identity->id,
                'type' => 'KTP',
                'number' => 'TAMPERED',
                'holder_name' => 'Tampered Holder',
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $rental = Rental::query()->firstOrFail();
        $collateral = RentalCollateral::query()->firstOrFail();

        $this->assertSame('0.00', $rental->deposit_amount);
        $this->assertSame($identity->id, $collateral->customer_identity_id);
        $this->assertSame('customer_identity', $collateral->source_type);
        $this->assertSame('SIM', $collateral->type);
        $this->assertSame('SIM-C360-001', $collateral->number);
        $this->assertSame('Nama Canonical', $collateral->holder_name);
        $this->assertSame($identity->id, $collateral->identity_snapshot['customer_identity_id']);
        $this->assertNotNull($collateral->identity_snapshot['verified_at']);
        $this->assertDatabaseMissing('payments', [
            'rental_id' => $rental->id,
            'type' => 'deposit',
            'status' => 'completed',
        ]);
    }

    public function test_checkout_rejects_customer360_identity_from_another_customer(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $otherCustomer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-OTHER-COLLATERAL',
            'name' => 'Pelanggan Lain',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $otherCustomer->id,
            'type' => 'ktp',
            'number' => 'OTHER-KTP-001',
            'is_primary' => true,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);
        $booking = $this->confirmedBooking($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'collaterals' => [[
                'customer_identity_id' => $identity->id,
                'type' => 'KTP',
                'number' => 'OTHER-KTP-001',
            ]],
        ])->assertSessionHasErrors('collaterals.0.customer_identity_id');

        $this->assertDatabaseCount('rentals', 0);
        $this->assertDatabaseCount('rental_collaterals', 0);
    }

    public function test_checkout_rejects_expired_customer360_identity_but_manual_override_remains_available(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'EXPIRED-KTP-001',
            'expires_at' => now()->subDay()->toDateString(),
            'is_primary' => true,
        ]);
        $booking = $this->confirmedBooking($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'collaterals' => [[
                'customer_identity_id' => $identity->id,
                'type' => 'KTP',
                'number' => 'EXPIRED-KTP-001',
            ]],
        ])->assertSessionHasErrors('collaterals.0.customer_identity_id');

        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'collaterals' => [[
                'type' => 'KTP',
                'number' => 'MANUAL-OVERRIDE-001',
                'holder_name' => $customer->name,
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $collateral = RentalCollateral::query()->firstOrFail();
        $this->assertNull($collateral->customer_identity_id);
        $this->assertSame('manual', $collateral->source_type);
        $this->assertNull($collateral->identity_snapshot);
        $this->assertSame('MANUAL-OVERRIDE-001', $collateral->number);
    }

    public function test_active_rental_can_receive_private_collateral_document_and_foreign_branch_cannot_read_it(): void
    {
        Storage::fake('local');
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.collaterals.store', $rental), [
            'source_mode' => 'manual',
            'type' => 'KTP',
            'number' => 'KTP-PRIVATE-01',
            'holder_name' => $customer->name,
            'physical_received' => true,
            'document' => UploadedFile::fake()->image('jaminan.jpg'),
        ])->assertSessionHasNoErrors();

        $collateral = RentalCollateral::query()->where('number', 'KTP-PRIVATE-01')->firstOrFail();
        $this->actingAs($user)
            ->get(route('rentals.collaterals.document', [$rental, $collateral]))
            ->assertOk();

        $otherBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $other = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $otherBranch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $other->branches()->attach($otherBranch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $other->roles()->attach(
            Role::query()->where('slug', 'rental-operator')->firstOrFail()->id,
            ['branch_id' => $otherBranch->id, 'assigned_at' => now()],
        );

        $this->actingAs($other)
            ->get(route('rentals.collaterals.document', [$rental, $collateral]))
            ->assertNotFound();
    }

    public function test_active_rental_page_exposes_customer360_identity_added_after_checkout(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'ktp',
            'number' => 'POST-CHECKOUT-KTP-001',
            'name_on_identity' => 'Identitas Setelah Checkout',
            'expires_at' => now()->addYear()->toDateString(),
            'is_primary' => true,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('rentals.show', $rental))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('rentals/show')
                ->where('customerIdentities.0.id', $identity->id)
                ->where('customerIdentities.0.number', 'POST-CHECKOUT-KTP-001')
                ->where('customerIdentities.0.is_default', true)
                ->where('permissions.updateCustomer', true));
    }

    public function test_active_rental_can_receive_existing_customer360_identity_added_after_checkout(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);
        $identity = CustomerIdentity::query()->create([
            'customer_id' => $customer->id,
            'type' => 'sim',
            'number' => 'POST-CHECKOUT-SIM-001',
            'name_on_identity' => 'Nama Canonical Setelah Checkout',
            'expires_at' => now()->addYear()->toDateString(),
            'is_primary' => true,
            'verified_at' => now(),
            'verified_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('rentals.collaterals.store', $rental), [
            'source_mode' => 'existing',
            'customer_identity_id' => $identity->id,
            'type' => 'KTP',
            'number' => 'TAMPERED',
            'holder_name' => 'Tampered Holder',
            'physical_received' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $collateral = RentalCollateral::query()->latest('id')->firstOrFail();

        $this->assertSame($identity->id, $collateral->customer_identity_id);
        $this->assertSame('customer_identity', $collateral->source_type);
        $this->assertSame('SIM', $collateral->type);
        $this->assertSame('POST-CHECKOUT-SIM-001', $collateral->number);
        $this->assertSame('Nama Canonical Setelah Checkout', $collateral->holder_name);
        $this->assertSame($identity->id, $collateral->identity_snapshot['customer_identity_id']);
        $this->assertSame('held', $collateral->status);
        $this->assertSame(2, $rental->collaterals()->where('status', 'held')->count());
    }

    public function test_active_rental_can_create_customer360_identity_and_receive_it_atomically(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.collaterals.store', $rental), [
            'source_mode' => 'new',
            'identity_type' => 'ktp',
            'identity_number' => '3502010101010777',
            'identity_name_on_identity' => 'Identitas Baru Rental',
            'identity_expires_at' => now()->addYears(2)->toDateString(),
            'identity_is_primary' => true,
            'save_to_customer360' => true,
            'physical_received' => true,
            'notes' => 'Dibuat saat rental aktif.',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $identity = CustomerIdentity::query()->where('number', '3502010101010777')->firstOrFail();
        $collateral = RentalCollateral::query()->latest('id')->firstOrFail();

        $this->assertSame($customer->id, $identity->customer_id);
        $this->assertTrue($identity->is_primary);
        $this->assertNull($identity->verified_at);
        $this->assertSame($identity->id, $collateral->customer_identity_id);
        $this->assertSame('customer_identity', $collateral->source_type);
        $this->assertSame('KTP', $collateral->type);
        $this->assertSame('3502010101010777', $collateral->number);
        $this->assertSame('held', $collateral->status);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'customer.identity_created',
            'subject_id' => $identity->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_received',
            'subject_id' => $collateral->id,
        ]);
    }

    public function test_new_identity_can_be_received_without_saving_to_customer360(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.collaterals.store', $rental), [
            'source_mode' => 'new',
            'identity_type' => 'passport',
            'identity_number' => 'A12345678',
            'identity_name_on_identity' => 'Passport Manual',
            'save_to_customer360' => false,
            'physical_received' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $collateral = RentalCollateral::query()->latest('id')->firstOrFail();

        $this->assertDatabaseCount('customer_identities', 0);
        $this->assertNull($collateral->customer_identity_id);
        $this->assertSame('manual', $collateral->source_type);
        $this->assertSame('Paspor', $collateral->type);
        $this->assertSame('A12345678', $collateral->number);
        $this->assertSame('Passport Manual', $collateral->holder_name);
        $this->assertNull($collateral->identity_snapshot);
    }

    public function test_active_rental_collateral_requires_physical_received_confirmation(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental($user, $branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('rentals.collaterals.store', $rental), [
            'source_mode' => 'manual',
            'type' => 'KTP',
            'number' => 'KTP-NOT-RECEIVED',
            'holder_name' => $customer->name,
        ])->assertSessionHasErrors('physical_received');

        // Direct Rental already has one mandatory initial collateral; the rejected
        // second receipt must not create another record.
        $this->assertDatabaseCount('rental_collaterals', 1);
        $this->assertDatabaseMissing('rental_collaterals', [
            'rental_id' => $rental->id,
            'number' => 'KTP-NOT-RECEIVED',
        ]);
    }

    public function test_final_return_is_atomic_until_all_held_collaterals_are_confirmed_returned(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
            quantity: 1,
            payInFull: true,
            collateral: true,
        );
        $unit = $rental->items()->firstOrFail()->assets()->firstOrFail();
        $collateral = $rental->collaterals()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasErrors('returned_collateral_ids');

        $this->assertSame('active', $rental->fresh()->status);
        $this->assertSame('out', $unit->fresh()->status);
        $this->assertSame('held', $collateral->fresh()->status);
        $this->assertDatabaseCount('rental_returns', 0);

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'returned_collateral_ids' => [$collateral->id],
            'items' => [[
                'rental_item_asset_id' => $unit->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame('returned', $collateral->fresh()->status);
        $this->assertSame($user->id, $collateral->fresh()->returned_by);
        $this->assertNotNull($collateral->fresh()->returned_at);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_returned',
            'subject_id' => $collateral->id,
        ]);
    }

    public function test_partial_return_can_keep_collateral_held_until_last_unit_returns(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture(2);
        $rental = $this->createDirectRental(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
            quantity: 2,
            payInFull: true,
            collateral: true,
        );
        $units = $rental->items()->firstOrFail()->assets()->get();
        $collateral = $rental->collaterals()->firstOrFail();

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->format('Y-m-d H:i:s'),
            'items' => [[
                'rental_item_asset_id' => $units[0]->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('partial_return', $rental->fresh()->status);
        $this->assertSame('held', $collateral->fresh()->status);

        $this->actingAs($user)->post(route('rentals.return.store', $rental), [
            'returned_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'returned_collateral_ids' => [$collateral->id],
            'items' => [[
                'rental_item_asset_id' => $units[1]->id,
                'condition' => 'good',
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $rental->fresh()->status);
        $this->assertSame('returned', $collateral->fresh()->status);
    }

    public function test_manual_collateral_return_is_guarded_against_duplicate_return(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $rental = $this->createDirectRental(
            $user,
            $branch,
            $customer,
            $plan,
            $product,
            collateral: true,
        );
        $collateral = $rental->collaterals()->firstOrFail();

        $this->actingAs($user)
            ->post(route('rentals.collaterals.return', [$rental, $collateral]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('rentals.collaterals.return', [$rental, $collateral]))
            ->assertStatus(409);

        $this->assertSame('returned', $collateral->fresh()->status);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'rental.collateral_returned',
            'subject_id' => $collateral->id,
        ]);
    }

    /** @return array{User, Branch, Customer, RatePlan, Product, Collection<int, Asset>} */
    private function fixture(int $assetCount = 1): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, [
            'is_default' => true,
            'is_active' => true,
        ]);
        $user->roles()->attach(
            Role::query()->where('slug', 'super-admin')->firstOrFail()->id,
            ['branch_id' => null, 'assigned_at' => now()],
        );
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-COLLATERAL',
            'name' => 'Pelanggan Collateral',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-COLLATERAL',
            'name' => 'Kamera Collateral',
            'tracking_type' => 'serialized',
            'is_rentable' => true,
            'is_active' => true,
        ]);
        ProductRate::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'rate_plan_id' => $plan->id,
            'amount' => 50000,
            'deposit_amount' => 100000,
            'is_active' => true,
        ]);
        $assets = collect();
        for ($index = 1; $index <= $assetCount; $index++) {
            $assets->push(Asset::query()->create([
                'product_id' => $product->id,
                'owning_branch_id' => $branch->id,
                'current_branch_id' => $branch->id,
                'asset_code' => sprintf('CAM-COL-%03d', $index),
                'status' => 'available',
                'condition' => 'good',
                'is_active' => true,
            ]));
        }

        return [$user, $branch, $customer, $plan, $product, $assets];
    }

    /** @return array<string, mixed> */
    private function bookingPayload(
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        int $quantity = 1,
    ): array {
        return [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'source' => 'counter',
            'starts_at' => now()->format('Y-m-d H:i:s'),
            'duration_units' => 1,
            'items' => [[
                'type' => 'product',
                'id' => $product->id,
                'quantity' => $quantity,
            ]],
        ];
    }

    private function confirmedBooking(
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

        $booking = Booking::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();

        return $booking->fresh();
    }

    /** @return array<string, mixed> */
    private function rentalPayload(
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        int $quantity = 1,
    ): array {
        return [
            ...$this->bookingPayload($branch, $customer, $plan, $product, $quantity),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
        ];
    }

    private function createDirectRental(
        User $user,
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        int $quantity = 1,
        bool $payInFull = false,
        bool $collateral = false,
    ): Rental {
        $payload = $this->rentalPayload($branch, $customer, $plan, $product, $quantity);

        if ($payInFull) {
            $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
            $session = $this->openCashSession($user, $branch);
            $payload['payment_amount'] = 50000 * $quantity;
            $payload['payment_method_id'] = $method->id;
            $payload['cash_session_id'] = $session->id;
        }

        // UAT-014: Direct Rental always requires an initial physical collateral.
        // Tests that add Customer360 identities later start with a distinct manual
        // item, while the return tests retain their explicit KTP fixture.
        $payload['collaterals'] = [[
            'type' => $collateral ? 'KTP' : 'Kartu Mahasiswa',
            'number' => $collateral ? 'KTP-HELD-001' : 'MHS-INITIAL-001',
            'holder_name' => $customer->name,
        ]];

        $this->actingAs($user)->post(route('rentals.direct.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect();

        return Rental::query()
            ->with(['items.assets.asset', 'collaterals'])
            ->latest('id')
            ->firstOrFail();
    }
}
