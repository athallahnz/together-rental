<?php

namespace Tests\Feature\Bookings;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class BookingManagementTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_booking_is_priced_and_assets_are_reserved_by_the_server(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();

        $this->actingAs($user)->post(route('bookings.store'), $this->payload(
            $branch, $customer, $plan, $product,
        ))->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();

        $this->assertSame('draft', $booking->status);
        $this->assertSame('150000.00', $booking->total_amount);
        $this->assertSame('500000.00', $booking->deposit_required);
        $this->assertTrue($booking->ends_at->equalTo($booking->starts_at->copy()->addDay()));
        $this->assertDatabaseCount('booking_items', 1);
        $this->assertDatabaseCount('asset_reservations', 1);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'to_status' => 'draft',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $booking->id,
            'event' => 'booking.created',
        ]);
    }

    public function test_booking_accepts_rental_dp_and_security_deposit_separately(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['payment_amount'] = 50000;
        $payload['deposit_paid'] = 200000;
        $payload['payment_method_id'] = $method->id;
        $payload['cash_session_id'] = $session->id;

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'rental_id' => null,
            'type' => 'rental',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'type' => 'deposit',
            'amount' => 200000,
        ]);
        $this->assertSame('200000.00', $booking->deposit_paid);
        $this->assertDatabaseCount('cash_transactions', 2);
    }

    public function test_booking_rejects_payment_above_remaining_bill(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($user)->post(
            route('bookings.store'),
            $this->payload($branch, $customer, $plan, $product),
        );
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($user)->post(route('bookings.payments.store', $booking), [
            'payment_amount' => 200000,
            'deposit_paid' => 0,
            'payment_method_id' => $method->id,
            'payment_reference' => 'TRX-OVERPAYMENT-TEST',
        ])->assertSessionHasErrors('payment_amount');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_overlapping_booking_cannot_reserve_the_same_asset(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $payload = $this->payload($branch, $customer, $plan, $product);

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('asset_reservations', 1);
    }

    public function test_rate_plan_duration_controls_end_time_and_multiplies_price(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['duration_units'] = 3;
        $payload['ends_at'] = now()->addYear()->format('Y-m-d H:i:s');

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();

        $this->assertSame('450000.00', $booking->total_amount);
        $this->assertTrue($booking->ends_at->equalTo($booking->starts_at->copy()->addDays(3)));
    }

    public function test_confirm_and_cancel_follow_the_controlled_lifecycle(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $this->actingAs($user)->post(
            route('bookings.store'),
            $this->payload($branch, $customer, $plan, $product),
        );
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $booking->fresh()->status);

        $this->actingAs($user)->post(route('bookings.cancel', $booking), [
            'reason' => 'Pelanggan membatalkan jadwal.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertDatabaseHas('asset_reservations', [
            'booking_id' => $booking->id,
            'status' => 'released',
        ]);
    }

    public function test_branch_scoped_operator_cannot_create_booking_for_another_branch(): void
    {
        [$administrator, $branch, $customer, $plan, $product] = $this->fixture();
        $other = Branch::query()->create([
            'company_id' => $administrator->company_id,
            'code' => 'MDO',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $operator = User::factory()->create([
            'company_id' => $administrator->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $role = Role::query()->where('slug', 'rental-operator')->firstOrFail();
        $operator->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $operator->roles()->attach($role->id, ['branch_id' => $branch->id, 'assigned_at' => now()]);

        $payload = $this->payload($branch, $customer, $plan, $product);
        $payload['branch_id'] = $other->id;

        $this->actingAs($operator)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors('branch_id');
    }

    public function test_booking_options_are_searched_server_side_and_scoped(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $product->update([
            'brand' => 'Sony',
            'model' => 'Alpha',
            'primary_image_path' => 'catalog/products/sony-alpha.webp',
        ]);

        $this->actingAs($user)
            ->getJson(route('bookings.options', [
                'type' => 'customer',
                'q' => 'Pelanggan',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id);

        $this->actingAs($user)
            ->getJson(route('bookings.options', [
                'type' => 'product',
                'q' => 'Sony',
                'branch_id' => $branch->id,
                'rate_plan_id' => $plan->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.brand', 'Sony')
            ->assertJsonPath('data.0.model', 'Alpha')
            ->assertJsonPath(
                'data.0.image_url',
                url('/storage/catalog/products/sony-alpha.webp'),
            );
    }

    /** @return array{User, Branch, Customer, RatePlan, Product} */
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
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach($role->id, ['branch_id' => null, 'assigned_at' => now()]);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-TEST',
            'name' => 'Pelanggan Booking',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-TEST',
            'name' => 'Kamera Test',
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
        Asset::query()->create([
            'product_id' => $product->id,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'CAM-TEST-001',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);

        return [$user, $branch, $customer, $plan, $product];
    }

    /** @return array<string, mixed> */
    private function payload(Branch $branch, Customer $customer, RatePlan $plan, Product $product): array
    {
        return [
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rate_plan_id' => $plan->id,
            'source' => 'counter',
            'starts_at' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'duration_units' => 1,
            'notes' => 'Booking feature test.',
            'items' => [['type' => 'product', 'id' => $product->id, 'quantity' => 1]],
        ];
    }
}
