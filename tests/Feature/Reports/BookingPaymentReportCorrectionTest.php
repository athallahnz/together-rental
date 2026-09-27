<?php

namespace Tests\Feature\Reports;

use App\Domain\Finance\BookingPaymentSettlement;
use App\Domain\Reporting\IntegratedReportService;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BookingPaymentReportCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_report_separates_rental_dp_and_security_deposit_and_paid_refunds(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$branch, $customer, $method] = $this->fixture();
        $withBoth = $this->booking($branch, $customer, 'PNG-BKG-RPT6-BOTH', 500000, 100000);
        $rentalPayment = $this->payment($withBoth, $method, 'rental', 150000, 'completed', 'PNG-PAY-RPT6-DP');
        $depositPayment = $this->payment($withBoth, $method, 'deposit', 100000, 'completed', 'PNG-PAY-RPT6-DEPOSIT');
        $this->payment($withBoth, $method, 'rental', 90000, 'void', 'PNG-PAY-RPT6-VOID');
        $this->payment($withBoth, $method, 'rental', 120000, 'pending', 'PNG-PAY-RPT6-PENDING');
        $this->refund($rentalPayment, $method, 50000, 'paid', 'PNG-RFD-RPT6-DP');
        $this->refund($rentalPayment, $method, 30000, 'approved', 'PNG-RFD-RPT6-APPROVED');
        $this->refund($depositPayment, $method, 20000, 'paid', 'PNG-RFD-RPT6-DEPOSIT');

        $depositOnly = $this->booking($branch, $customer, 'PNG-BKG-RPT6-DEPOSIT', 300000, 200000);
        $this->payment($depositOnly, $method, 'deposit', 200000, 'completed', 'PNG-PAY-RPT6-ONLY');
        $withoutPayment = $this->booking($branch, $customer, 'PNG-BKG-RPT6-NONE', 200000, 0);

        $report = app(IntegratedReportService::class)->generate(
            (int) $branch->company_id,
            [$branch->id],
            $this->filters(),
        );
        $byNumber = collect($report['rows'])
            ->filter(static fn (array $row): bool => $row['values']['kind'] === 'Booking')
            ->keyBy(static fn (array $row): string => (string) $row['values']['number']);

        $this->assertSame(3, $byNumber->count());
        $this->assertEqualsWithDelta(100000, $byNumber[$withBoth->booking_number]['values']['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(400000, $byNumber[$withBoth->booking_number]['values']['balance_due'], 0.01);
        $this->assertEqualsWithDelta(0, $byNumber[$depositOnly->booking_number]['values']['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(300000, $byNumber[$depositOnly->booking_number]['values']['balance_due'], 0.01);
        $this->assertEqualsWithDelta(0, $byNumber[$withoutPayment->booking_number]['values']['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(200000, $byNumber[$withoutPayment->booking_number]['values']['balance_due'], 0.01);

        $settlement = app(BookingPaymentSettlement::class)->summary($withBoth);
        $this->assertEqualsWithDelta($settlement['rental_paid'], $byNumber[$withBoth->booking_number]['values']['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(80000, $settlement['deposit_paid'], 0.01);
        $this->assertEqualsWithDelta(100000, $withBoth->fresh()->deposit_paid, 0.01); // Report must not mutate bookings.
    }

    public function test_multiple_paid_refunds_are_not_double_counted_and_each_source_payment_is_clamped(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$branch, $customer, $method] = $this->fixture();
        $booking = $this->booking($branch, $customer, 'PNG-BKG-RPT6-MULTI', 250000, 0);
        $first = $this->payment($booking, $method, 'rental', 100000, 'completed', 'PNG-PAY-RPT6-MULTI1');
        $this->payment($booking, $method, 'rental', 50000, 'completed', 'PNG-PAY-RPT6-MULTI2');
        $this->refund($first, $method, 80000, 'paid', 'PNG-RFD-RPT6-MULTI1');
        $this->refund($first, $method, 40000, 'paid', 'PNG-RFD-RPT6-MULTI2');
        $this->refund($first, $method, 15000, 'requested', 'PNG-RFD-RPT6-MULTI3');

        $report = app(IntegratedReportService::class)->generate((int) $branch->company_id, [$branch->id], $this->filters());
        $bookingRow = collect($report['rows'])->firstWhere('id', 'booking-'.$booking->id);

        $this->assertNotNull($bookingRow);
        $this->assertEqualsWithDelta(50000, $bookingRow['values']['paid_amount'], 0.01);
        $this->assertEqualsWithDelta(200000, $bookingRow['values']['balance_due'], 0.01);
        $this->assertEqualsWithDelta(
            app(BookingPaymentSettlement::class)->net($booking, 'rental'),
            $bookingRow['values']['paid_amount'],
            0.01,
        );
    }

    public function test_operational_page_and_excel_export_keep_branch_scope_and_correct_booking_amount(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$branch, $customer, $method] = $this->fixture();
        $manager = $this->manager($branch);
        $local = $this->booking($branch, $customer, 'PNG-BKG-RPT6-EXPORT', 500000, 100000);
        $this->payment($local, $method, 'rental', 150000, 'completed', 'PNG-PAY-RPT6-EXPORT');
        $this->payment($local, $method, 'deposit', 100000, 'completed', 'PNG-PAY-RPT6-EXPORTD');

        $otherBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $other = $this->booking($otherBranch, $customer, 'MDN-BKG-RPT6-PRIVATE', 800000, 0);
        $this->payment($other, $method, 'rental', 300000, 'completed', 'MDN-PAY-RPT6-PRIVATE');

        $params = ['report' => 'operational', 'from' => '2026-09-01', 'to' => '2026-09-26'];
        $this->actingAs($manager)->get(route('reports.index', $params))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reportMeta.row_count', 1)
                ->where('rows.data.0.values.number', $local->booking_number)
                ->where('rows.data.0.values.paid_amount', 150000)
                ->where('rows.data.0.values.balance_due', 350000));

        $excel = $this->actingAs($manager)->get(route('reports.export', [...$params, 'format' => 'excel']));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $xml = (string) $excel->getContent();
        $this->assertStringContainsString($local->booking_number, $xml);
        $this->assertStringContainsString('>150000</Data>', $xml);
        $this->assertStringContainsString('>350000</Data>', $xml);
        $this->assertStringNotContainsString($other->booking_number, $xml);
    }

    public function test_live_uat_auditor_refuses_a_non_uat_database(): void
    {
        $this->artisan('reports:audit-booking-payments', [
            '--from' => '2026-09-01',
            '--to' => '2026-09-26',
            '--branch' => 'PNG',
        ])->assertFailed();
    }

    /** @return array{Branch, Customer, PaymentMethod} */
    private function fixture(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-RPT6',
            'name' => 'Pelanggan Uji Laporan',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();

        return [$branch, $customer, $method];
    }

    private function booking(Branch $branch, Customer $customer, string $number, float $total, float $deposit): Booking
    {
        return Booking::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'booking_number' => $number,
            'status' => 'confirmed',
            'source' => 'counter',
            'booked_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'total_amount' => $total,
            'deposit_required' => $deposit,
            'deposit_paid' => $deposit,
        ]);
    }

    private function payment(Booking $booking, PaymentMethod $method, string $type, float $amount, string $status, string $number): Payment
    {
        return Payment::query()->create([
            'branch_id' => $booking->branch_id,
            'customer_id' => $booking->customer_id,
            'booking_id' => $booking->id,
            'payment_method_id' => $method->id,
            'payment_number' => $number,
            'direction' => 'in',
            'type' => $type,
            'source_context' => 'booking',
            'status' => $status,
            'amount' => $amount,
            'paid_at' => now(),
        ]);
    }

    private function refund(Payment $payment, PaymentMethod $method, float $amount, string $status, string $number): Refund
    {
        return Refund::query()->create([
            'branch_id' => $payment->branch_id,
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'payment_method_id' => $method->id,
            'refund_number' => $number,
            'refund_type' => 'partial',
            'amount' => $amount,
            'status' => $status,
            'reason' => 'UAT Booking Report Stage 6A.',
            'processed_at' => $status === 'paid' ? now() : null,
        ]);
    }

    private function manager(Branch $branch): User
    {
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach(
            Role::query()->where('slug', 'branch-manager')->firstOrFail()->id,
            ['branch_id' => $branch->id, 'assigned_at' => now()],
        );

        return $user;
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return [
            'report' => 'operational',
            'from' => CarbonImmutable::parse('2026-09-01')->startOfDay(),
            'to' => CarbonImmutable::parse('2026-09-26')->endOfDay(),
            'branch_id' => null,
            'status' => 'all',
            'payment_method_id' => null,
            'category_id' => null,
            'search' => '',
        ];
    }
}
