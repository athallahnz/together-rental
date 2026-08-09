<?php

namespace Tests\Feature\Finance;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Payment;
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

class PaymentIntegrationTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_cash_booking_payment_requires_session_and_writes_one_ledger_row_per_payment(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $payload = $this->payload($branch, $customer, $plan, $product, [
            'payment_amount' => 50000,
            'deposit_paid' => 200000,
            'payment_method_id' => $method->id,
        ]);

        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasErrors('cash_session_id');
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);

        $session = $this->openCashSession($user, $branch, 100000);
        $payload['cash_session_id'] = $session->id;
        $this->actingAs($user)->post(route('bookings.store'), $payload)
            ->assertSessionHasNoErrors();

        $booking = Booking::query()->firstOrFail();
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'cash_session_id' => $session->id,
            'source_context' => 'booking',
            'type' => 'rental',
            'amount' => 50000,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_session_id' => $session->id,
            'direction' => 'in',
            'type' => 'deposit',
            'balance_after' => 350000,
        ]);
    }

    public function test_noncash_payment_does_not_require_or_write_cash_session(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)->post(route('bookings.store'), $this->payload(
            $branch,
            $customer,
            $plan,
            $product,
            [
                'payment_amount' => 50000,
                'payment_method_id' => $method->id,
                'payment_reference' => 'TRX-BANK-001',
            ],
        ))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payments', [
            'payment_method_id' => $method->id,
            'cash_session_id' => null,
            'source_context' => 'booking',
            'amount' => 50000,
        ]);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_cash_session_from_another_branch_is_rejected(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        CashRegister::query()->create([
            'branch_id' => $other->id,
            'code' => 'MAIN',
            'name' => 'Kas Utama Madiun',
            'is_active' => true,
        ]);
        $otherSession = $this->openCashSession($user, $other);

        $this->actingAs($user)->post(route('bookings.store'), $this->payload(
            $branch,
            $customer,
            $plan,
            $product,
            [
                'payment_amount' => 50000,
                'payment_method_id' => $method->id,
                'cash_session_id' => $otherSession->id,
            ],
        ))->assertSessionHasErrors('cash_session_id');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_void_is_idempotent_and_appends_cash_reversal_without_deleting_payment(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);

        $this->actingAs($user)->post(route('bookings.store'), $this->payload(
            $branch,
            $customer,
            $plan,
            $product,
            [
                'deposit_paid' => 200000,
                'payment_method_id' => $method->id,
                'cash_session_id' => $session->id,
            ],
        ))->assertSessionHasNoErrors();

        $payment = Payment::query()->where('type', 'deposit')->firstOrFail();
        $booking = Booking::query()->firstOrFail();
        $payload = ['reason' => 'Nominal deposit salah input oleh kasir.'];

        $this->actingAs($user)
            ->post(route('finance.payments.void', $payment), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('finance.payments.void', $payment), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame('void', $payment->fresh()->status);
        $this->assertSame('0.00', $booking->fresh()->deposit_paid);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('cash_transactions', [
            'payment_id' => $payment->id,
            'direction' => 'out',
            'type' => 'deposit_void',
            'amount' => 200000,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $payment->id,
            'event' => 'payment.voided',
        ]);
    }

    public function test_cash_payment_from_closed_session_requires_refund_instead_of_void(): void
    {
        [$user, $branch, $customer, $plan, $product] = $this->fixture();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);

        $this->actingAs($user)->post(route('bookings.store'), $this->payload(
            $branch,
            $customer,
            $plan,
            $product,
            [
                'payment_amount' => 50000,
                'payment_method_id' => $method->id,
                'cash_session_id' => $session->id,
            ],
        ))->assertSessionHasNoErrors();
        $session->update(['status' => 'closed', 'closed_at' => now()]);
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($user)->post(route('finance.payments.void', $payment), [
            'reason' => 'Pembayaran akan dikoreksi setelah tutup kas.',
        ])->assertSessionHasErrors('cash_session_id');

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertDatabaseCount('cash_transactions', 1);
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
            'customer_number' => 'PNG-CUS-FIN-001',
            'name' => 'Pelanggan Finance',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-FIN-001',
            'name' => 'Kamera Finance Test',
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
            'asset_code' => 'CAM-FIN-001',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);

        return [$user, $branch, $customer, $plan, $product];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function payload(
        Branch $branch,
        Customer $customer,
        RatePlan $plan,
        Product $product,
        array $payment = [],
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
            ...$payment,
        ];
    }
}
