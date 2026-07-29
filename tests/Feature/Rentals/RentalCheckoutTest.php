<?php

namespace Tests\Feature\Rentals;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalCheckoutTest extends TestCase
{
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
        ])->assertSessionHasNoErrors();

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
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $rental->id,
            'event' => 'rental.booking_checked_out',
        ]);
    }

    public function test_direct_rental_creates_internal_booking_and_checks_out(): void
    {
        [$user, $branch, $customer, $plan, $product, $asset] = $this->fixture();

        $this->actingAs($user)->post(route('rentals.direct.store'), [
            ...$this->bookingPayload($branch, $customer, $plan, $product),
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'checkout_condition' => 'good',
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();
        $rental = Rental::query()->firstOrFail();

        $this->assertSame('direct', $booking->source);
        $this->assertSame('converted', $booking->status);
        $this->assertSame($booking->id, $rental->booking_id);
        $this->assertSame('active', $rental->status);
        $this->assertSame('rented', $asset->fresh()->status);
        $this->assertDatabaseHas('rental_status_histories', [
            'rental_id' => $rental->id,
            'to_status' => 'active',
        ]);
    }

    public function test_booking_payments_are_carried_into_rental_balance(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $payload = $this->bookingPayload($branch, $customer, $plan, $product);
        $payload['payment_amount'] = 50000;
        $payload['deposit_paid'] = 200000;
        $payload['payment_method_id'] = $method->id;

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
        ])->assertSessionHasNoErrors();

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
