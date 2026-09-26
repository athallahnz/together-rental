<?php

namespace Tests\Feature\Finance;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductRate;
use App\Models\RatePlan;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class RefundBookingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_partial_booking_dp_refund_reduces_net_dp_but_preserves_original_payment(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();

        $refund = $this->payRefund($user, $approver, $payment, 40000);
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('100000.00', $payment->fresh()->amount);
        $this->assertSame('200000.00', $booking->fresh()->deposit_paid);

        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 60000)
                ->where('financialSummary.rental_refunded', 40000)
                ->where('financialSummary.deposit_paid', 200000)
                ->where('financialSummary.deposit_refunded', 0)
                ->has('booking.payments', 2));

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseCount('cash_transactions', 0);

        // Refunded DP is available to be repaid without overpayment rejection.
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($user)->post(route('bookings.payments.store', $booking), [
            'payment_amount' => 90000,
            'deposit_paid' => 0,
            'payment_method_id' => $method->id,
            'payment_reference' => 'TRX-DP-REPAID-001',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payments', 3);
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 150000)
                ->where('financialSummary.rental_refunded', 40000));
    }

    public function test_paid_deposit_refund_reduces_only_security_deposit_and_reopens_deposit_due(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $deposit = $booking->payments()->where('type', 'deposit')->firstOrFail();

        $this->payRefund($user, $approver, $deposit, 50000);

        $this->assertSame('150000.00', $booking->fresh()->deposit_paid);
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 100000)
                ->where('financialSummary.rental_refunded', 0)
                ->where('financialSummary.deposit_paid', 150000)
                ->where('financialSummary.deposit_refunded', 50000));

        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($user)->post(route('bookings.payments.store', $booking), [
            'payment_amount' => 0,
            'deposit_paid' => 350000,
            'payment_method_id' => $method->id,
            'payment_reference' => 'TRX-DEPOSIT-REPAID-001',
        ])->assertSessionHasNoErrors();
        $this->assertSame('500000.00', $booking->fresh()->deposit_paid);
    }

    public function test_booking_dp_refunded_before_checkout_is_not_carried_as_gross_rental_payment(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $this->payRefund($user, $approver, $payment, 40000);
        $asset = Asset::query()->where('product_id', $product->id)->firstOrFail();

        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'assets' => [[
                'asset_id' => $asset->id,
                'condition' => 'good',
            ]],
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();

        $rental = Rental::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('60000.00', $rental->paid_amount);
        $this->assertSame('60000.00', $rental->booking_payment_amount);
        $this->assertSame('90000.00', $rental->balance_due);
        $this->assertSame('200000.00', $rental->deposit_amount);
        $this->assertSame('converted', $booking->fresh()->status);
    }

    public function test_booking_dp_refund_after_checkout_reconciles_both_booking_and_rental_once(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $asset = Asset::query()->where('product_id', $product->id)->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'assets' => [[
                'asset_id' => $asset->id,
                'condition' => 'good',
            ]],
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();
        $rental = Rental::query()->where('booking_id', $booking->id)->firstOrFail();
        $this->assertSame('100000.00', $rental->paid_amount);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();

        $refund = $this->payRefund($user, $approver, $payment, 40000);
        $rental->refresh();
        $this->assertSame('60000.00', $rental->paid_amount);
        $this->assertSame('60000.00', $rental->booking_payment_amount);
        $this->assertSame('90000.00', $rental->balance_due);
        $this->assertSame('200000.00', $rental->deposit_amount);
        $this->assertSame('150000.00', $rental->total_amount);
        $this->assertSame('100000.00', $payment->fresh()->amount);

        $this->actingAs($user)->post(route('finance.refunds.process', $refund), [
            'external_reference' => 'REFUND-DUPLICATE',
            'proof' => UploadedFile::fake()->image('duplicate.jpg'),
        ])->assertSessionHasErrors('refund');
        $this->assertSame('60000.00', $rental->fresh()->paid_amount);
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_security_deposit_refund_after_checkout_does_not_touch_rental_dp_or_outstanding(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $asset = Asset::query()->where('product_id', $product->id)->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'assets' => [[
                'asset_id' => $asset->id,
                'condition' => 'good',
            ]],
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();
        $rental = Rental::query()->where('booking_id', $booking->id)->firstOrFail();
        $depositPayment = $booking->payments()->where('type', 'deposit')->firstOrFail();

        $this->payRefund($user, $approver, $depositPayment, 50000);

        $rental->refresh();
        $this->assertSame('150000.00', $booking->fresh()->deposit_paid);
        $this->assertSame('150000.00', $rental->deposit_amount);
        $this->assertSame('100000.00', $rental->paid_amount);
        $this->assertSame('100000.00', $rental->booking_payment_amount);
        $this->assertSame('50000.00', $rental->balance_due);
    }

    public function test_requested_or_cancelled_refund_does_not_reduce_booking_dp(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => 20000,
            'payment_method_id' => $method->id,
            'purpose' => Refund::PURPOSE_PAYMENT_CORRECTION,
            'reason' => 'Permintaan refund yang akan dibatalkan.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->firstOrFail();
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 100000)
                ->where('financialSummary.rental_refunded', 0));
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasNoErrors();
        $this->actingAs($approver)->post(route('finance.refunds.cancel', $refund), [
            'reason' => 'Permintaan batal sebelum payout.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $refund->fresh()->status);

        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 100000)
                ->where('financialSummary.rental_refunded', 0));
        $this->assertSame('200000.00', $booking->fresh()->deposit_paid);
    }

    public function test_booking_dp_refund_requires_explicit_valid_purpose(): void
    {
        [$user,, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $payload = [
            'amount' => 100000,
            'payment_method_id' => $method->id,
            'reason' => 'Verifikasi keharusan memilih tujuan refund.',
        ];

        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), $payload)
            ->assertSessionHasErrors('purpose');
        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            ...$payload,
            'purpose' => 'other',
        ])->assertSessionHasErrors('purpose');

        $this->assertDatabaseCount('refunds', 0);
        $this->assertSame('draft', $booking->fresh()->status);
    }

    public function test_full_dp_payment_correction_preserves_confirmed_booking_and_reservations(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();

        $refund = $this->payRefund($user, $approver, $payment, 100000);
        $this->assertSame(Refund::PURPOSE_PAYMENT_CORRECTION, $refund->purpose);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, $booking->reservations()->where('status', 'reserved')->count());
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 0)
                ->where('financialSummary.rental_refunded', 100000));
    }

    public function test_booking_cancellation_refund_releases_stock_when_approved_before_payout(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => 100000,
            'payment_method_id' => $method->id,
            'purpose' => Refund::PURPOSE_BOOKING_CANCELLATION,
            'reason' => 'Pelanggan membatalkan reservasi sebelum pengambilan kamera.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->latest('id')->firstOrFail();

        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(1, $booking->reservations()->where('status', 'reserved')->count());
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasNoErrors();

        $this->assertSame('approved', $refund->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->cancelled_at);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'to_status' => 'cancelled',
            'changed_by' => $approver->id,
        ]);
        $this->assertSame(0, $booking->reservations()->where('status', 'reserved')->count());
        $this->assertSame(1, $booking->reservations()->where('status', 'released')->count());
        // No settlement occurs on approval. Deposit must be refunded separately.
        $this->assertSame('200000.00', $booking->fresh()->deposit_paid);
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 100000)
                ->where('financialSummary.rental_refunded', 0));

        $this->actingAs($user)->post(route('finance.refunds.process', $refund), [
            'external_reference' => 'REFUND-CANCEL-001',
            'proof' => UploadedFile::fake()->image('cancellation.jpg'),
        ])->assertSessionHasNoErrors();
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 0)
                ->where('financialSummary.rental_refunded', 100000));
    }

    public function test_rejected_cancellation_refund_does_not_cancel_booking(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => 100000,
            'payment_method_id' => $method->id,
            'purpose' => Refund::PURPOSE_BOOKING_CANCELLATION,
            'reason' => 'Pembatalan menunggu keputusan petugas yang berwenang.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->latest('id')->firstOrFail();
        $this->actingAs($approver)->post(route('finance.refunds.reject', $refund), [
            'reason' => 'Dokumen pembatalan belum lengkap.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $refund->fresh()->status);
        $this->assertSame('draft', $booking->fresh()->status);
        $this->assertSame(1, $booking->reservations()->where('status', 'reserved')->count());
    }

    public function test_cancelling_approved_cancellation_refund_never_reopens_booking_or_rewrites_dp(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => 100000,
            'payment_method_id' => $method->id,
            'purpose' => Refund::PURPOSE_BOOKING_CANCELLATION,
            'reason' => 'Pelanggan mengajukan pembatalan karena perubahan jadwal.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->latest('id')->firstOrFail();
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasNoErrors();
        $this->actingAs($approver)->post(route('finance.refunds.cancel', $refund), [
            'reason' => 'Metode pengembalian gagal, harus ajukan refund pengganti.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $refund->fresh()->status);
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(1, $booking->reservations()->where('status', 'released')->count());
        $this->actingAs($user)->get(route('bookings.show', $booking))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('financialSummary.rental_paid', 100000)
                ->where('financialSummary.rental_refunded', 0));
    }

    public function test_refund_approver_without_booking_cancel_permission_cannot_approve_cancellation(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => 100000,
            'payment_method_id' => $method->id,
            'purpose' => Refund::PURPOSE_BOOKING_CANCELLATION,
            'reason' => 'Pelanggan meminta pembatalan dan pengembalian DP.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->latest('id')->firstOrFail();

        $approver->roles()->detach();
        $approver->roles()->attach(
            Role::query()->where('slug', 'owner-management')->firstOrFail()->id,
            ['branch_id' => null, 'assigned_at' => now()],
        );
        $approver->unsetRelation('roles');
        $this->assertTrue($approver->can('refunds.approve'));
        $this->assertFalse($approver->can('bookings.cancel'));

        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasErrors('refund');
        $this->assertSame('requested', $refund->fresh()->status);
        $this->assertSame('draft', $booking->fresh()->status);
        $this->assertSame(1, $booking->reservations()->where('status', 'reserved')->count());
    }

    public function test_cancellation_refund_cannot_be_approved_after_booking_converts_to_rental(): void
    {
        [$user, $approver, $branch, $customer, $plan, $product] = $this->fixture();
        $booking = $this->booking($user, $branch, $customer, $plan, $product);
        $payment = $booking->payments()->where('type', 'rental')->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => 100000,
            'payment_method_id' => $method->id,
            'purpose' => Refund::PURPOSE_BOOKING_CANCELLATION,
            'reason' => 'Pelanggan meminta pembatalan sebelum rental dibuat.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->latest('id')->firstOrFail();
        $this->actingAs($user)->post(route('bookings.confirm', $booking))
            ->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('rentals.checkout.store', $booking), [
            'checked_out_at' => now()->format('Y-m-d H:i:s'),
            'assets' => [[
                'asset_id' => Asset::query()->where('product_id', $product->id)->firstOrFail()->id,
                'condition' => 'good',
            ]],
            'payment_amount' => 0,
            'deposit_paid' => 0,
        ])->assertSessionHasNoErrors();

        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasErrors('purpose');
        $this->assertSame('requested', $refund->fresh()->status);
        $this->assertSame('converted', $booking->fresh()->status);
        $this->assertDatabaseCount('rentals', 1);
    }

    /** @return array{User, User, Branch, Customer, RatePlan, Product} */
    private function fixture(): array
    {
        Storage::fake('local');
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = $this->user($branch);
        $approver = $this->user($branch);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-REFUND-INTEGRATION',
            'name' => 'Customer Refund Integration',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $plan = RatePlan::query()->where('code', '1D')->firstOrFail();
        $product = Product::query()->create([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-REFUND-001',
            'name' => 'Kamera Refund Test',
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
            'asset_code' => 'CAM-REFUND-001',
            'status' => 'available',
            'condition' => 'good',
            'is_active' => true,
        ]);

        return [$user, $approver, $branch, $customer, $plan, $product];
    }

    private function user(Branch $branch): User
    {
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

        return $user;
    }

    private function booking(User $user, Branch $branch, Customer $customer, RatePlan $plan, Product $product): Booking
    {
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($user)->post(route('bookings.store'), [
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
            'payment_amount' => 100000,
            'deposit_paid' => 200000,
            'payment_method_id' => $method->id,
            'payment_reference' => 'TRX-BOOKING-REFUND-SOURCE',
        ])->assertSessionHasNoErrors();

        return Booking::query()->firstOrFail();
    }

    private function payRefund(User $requester, User $approver, Payment $payment, float $amount): Refund
    {
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($requester)->post(route('finance.payments.refunds.store', $payment), [
            'amount' => $amount,
            'payment_method_id' => $method->id,
            ...($payment->type === 'rental' && $payment->source_context === 'booking'
                ? ['purpose' => Refund::PURPOSE_PAYMENT_CORRECTION]
                : []),
            'reason' => 'Refund pembayaran booking untuk pengujian integrasi.',
        ])->assertSessionHasNoErrors();
        $refund = Refund::query()->latest('id')->firstOrFail();
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasNoErrors();
        $this->actingAs($requester)->post(route('finance.refunds.process', $refund), [
            'external_reference' => 'TRX-BOOKING-REFUND-'.(string) $refund->id,
            'proof' => UploadedFile::fake()->image('booking-refund.jpg'),
        ])->assertSessionHasNoErrors();

        return $refund;
    }
}
