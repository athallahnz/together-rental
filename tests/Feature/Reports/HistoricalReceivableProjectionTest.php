<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class HistoricalReceivableProjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_later_payment_and_return_do_not_erase_historical_receivable_or_mix_deposit(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$manager, $branch, $customer, $method] = $this->fixture();
        $id = $this->rental($branch, $customer, 'PNG-RNT-ASOF-001', 500000, 0, 500000,
            'completed', '2026-09-24 12:00:00');
        $this->recordRentalStatus($id, 'active', '2026-09-22 08:00:00');
        $this->recordRentalStatus($id, 'completed', '2026-09-24 12:00:00');
        $this->payment($id, $branch, $customer, $method, 'PNG-PAY-ASOF-1', 150000, 'rental', 'completed', '2026-09-22 09:00:00');
        $this->payment($id, $branch, $customer, $method, 'PNG-PAY-ASOF-2', 350000, 'rental', 'completed', '2026-09-24 11:00:00');
        $this->payment($id, $branch, $customer, $method, 'PNG-PAY-ASOF-3', 100000, 'deposit', 'completed', '2026-09-22 10:00:00');
        $this->return($id, $branch, '2026-09-24 12:00:00', 0);

        $filters = ['report' => 'receivables', 'from' => '2026-09-01', 'to' => '2026-09-23'];
        $this->actingAs($manager)->get(route('reports.index', $filters))
            ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->where('historyMeta.as_of', '2026-09-23')
                ->where('historyMeta.verified_count', 1)
                ->where('historyMeta.unverified_count', 0)
                ->where('historyMeta.verified_receivables', 350000)
                ->where('reportMeta.row_count', 1)
                ->where('summary.4.value', 350000)
                ->where('branchPerformance.0.receivables', 350000)
                ->where('rows.data.0.values.status', 'active')
                ->where('rows.data.0.values.total_amount', 500000)
                ->where('rows.data.0.values.paid_amount', 150000)
                ->where('rows.data.0.values.balance_due', 350000));

        $excel = $this->actingAs($manager)->get(route('reports.export', [...$filters, 'format' => 'excel']));
        $excel->assertOk();
        $this->assertStringContainsString('350000', $excel->getContent());
        $this->assertStringContainsString('POSISI HISTORIS', $excel->getContent());
        $this->assertSame(0.0, (float) DB::table('rentals')->where('id', $id)->value('balance_due'));
    }

    public function test_asof_void_and_refund_times_are_attributed_to_the_correct_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$manager, $branch, $customer, $method] = $this->fixture();
        $id = $this->rental($branch, $customer, 'PNG-RNT-ASOF-002', 500000, 430000, 70000);
        $this->recordRentalStatus($id, 'active', '2026-09-22 08:00:00');
        $first = $this->payment($id, $branch, $customer, $method, 'PNG-PAY-VOID-1', 100000, 'rental', 'completed', '2026-09-22 09:00:00');
        $this->payment($id, $branch, $customer, $method, 'PNG-PAY-VOID-2', 30000, 'rental', 'void', '2026-09-22 10:00:00', '2026-09-24 12:00:00');
        $this->payment($id, $branch, $customer, $method, 'PNG-PAY-VOID-3', 20000, 'rental', 'void', '2026-09-22 10:00:00', '2026-09-22 12:00:00');
        $this->refund($first, $branch, $method, 'PNG-RFD-ASOF-1', 20000, '2026-09-23 12:00:00');
        $this->refund($first, $branch, $method, 'PNG-RFD-ASOF-2', 10000, '2026-09-25 12:00:00');

        $this->actingAs($manager)->get(route('reports.index', [
            'report' => 'receivables', 'from' => '2026-09-01', 'to' => '2026-09-23',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('historyMeta.unverified_count', 0)
            ->where('rows.data.0.values.paid_amount', 110000)
            ->where('rows.data.0.values.balance_due', 390000));
    }

    public function test_later_price_changes_and_legacy_records_are_explicitly_unverifiable(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$manager, $branch, $customer] = $this->fixture();
        $id = $this->rental($branch, $customer, 'PNG-RNT-ASOF-PRICE', 550000, 550000, 0,
            'completed', '2026-09-24 12:00:00');
        $this->recordRentalStatus($id, 'active', '2026-09-22 08:00:00');
        $this->return($id, $branch, '2026-09-24 12:00:00', 50000);
        $legacy = $this->rental($branch, $customer, 'PNG-RNT-ASOF-LEGACY', 300000, 300000, 0,
            'active', null, 'OLD-001');
        $this->recordRentalStatus($legacy, 'active', '2026-09-22 08:00:00');

        $this->actingAs($manager)->get(route('reports.index', [
            'report' => 'receivables', 'from' => '2026-09-01', 'to' => '2026-09-23',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('historyMeta.is_partial', true)
            ->where('historyMeta.unverified_count', 2)
            ->where('historyMeta.verified_receivables', 0)
            ->where('reportMeta.row_count', 2)
            ->where('rows.data.0.values.balance_due', null)
            ->where('rows.data.1.values.balance_due', null));
    }

    public function test_historical_report_preserves_branch_isolation_and_status_filter(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-26 12:00:00'));
        [$manager, $png, $customer, $method] = $this->fixture();
        $mdn = Branch::query()->create([
            'company_id' => $png->company_id,
            'code' => 'MDN', 'name' => 'Together Kamera Madiun', 'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $mdnCustomer = Customer::query()->create([
            'company_id' => $png->company_id, 'registered_branch_id' => $mdn->id,
            'customer_number' => 'MDN-CUS-ASOF-001', 'name' => 'Pelanggan MDN',
            'status' => 'active', 'risk_level' => 'normal',
        ]);
        $id = $this->rental($png, $customer, 'PNG-RNT-ASOF-ISOLATION', 200000, 200000, 0);
        $this->recordRentalStatus($id, 'active', '2026-09-22 08:00:00');
        $otherId = $this->rental($mdn, $mdnCustomer, 'MDN-RNT-ASOF-ISOLATION', 300000, 300000, 0);
        $this->recordRentalStatus($otherId, 'active', '2026-09-22 08:00:00');

        $this->actingAs($manager)->get(route('reports.index', [
            'report' => 'receivables', 'from' => '2026-09-01', 'to' => '2026-09-23',
            'status' => 'active',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('historyMeta.verified_receivables', 200000)
            ->where('reportMeta.row_count', 1)
            ->where('rows.data.0.values.rental_number', 'PNG-RNT-ASOF-ISOLATION'));
    }

    public function test_today_keeps_existing_current_state_report_contract(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00'));
        [$manager, $branch, $customer] = $this->fixture();
        $this->rental($branch, $customer, 'PNG-RNT-TODAY-CONTRACT', 400000, 125000, 275000);

        $this->actingAs($manager)->get(route('reports.index', [
            'report' => 'receivables', 'from' => '2026-09-01', 'to' => '2026-09-23',
        ]))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('reportMeta.row_count', 1)
            ->where('rows.data.0.values.balance_due', 125000)
            ->missing('historyMeta'));
    }

    /** @return array{User, Branch, Customer, PaymentMethod} */
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
        $user->branches()->attach($branch->id, ['is_default' => true, 'is_active' => true]);
        $user->roles()->attach(
            Role::query()->where('slug', 'branch-manager')->firstOrFail()->id,
            ['branch_id' => $branch->id, 'assigned_at' => now()],
        );
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-ASOF-001', 'name' => 'Pelanggan UAT Historis',
            'status' => 'active', 'risk_level' => 'normal',
        ]);
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();

        return [$user, $branch, $customer, $method];
    }

    private function rental(Branch $branch, Customer $customer, string $number, float $total,
        float $balance, float $paid, string $status = 'active', ?string $returned = null,
        ?string $legacy = null): int
    {
        return (int) DB::table('rentals')->insertGetId([
            'branch_id' => $branch->id, 'customer_id' => $customer->id,
            'rental_number' => $number, 'legacy_number' => $legacy, 'status' => $status,
            'checked_out_at' => '2026-09-22 08:00:00', 'due_at' => '2026-09-25 08:00:00',
            'returned_at' => $returned,
            'total_amount' => $total, 'paid_amount' => $paid, 'balance_due' => $balance,
            'deposit_amount' => 100000,
            'created_at' => '2026-09-22 08:00:00', 'updated_at' => '2026-09-24 12:00:00',
        ]);
    }

    private function recordRentalStatus(int $rentalId, string $status, string $when): void
    {
        DB::table('rental_status_histories')->insert([
            'rental_id' => $rentalId, 'to_status' => $status, 'changed_at' => $when,
            'created_at' => $when, 'updated_at' => $when,
        ]);
    }

    private function payment(int $rentalId, Branch $branch, Customer $customer, PaymentMethod $method,
        string $number, float $amount, string $type, string $status, string $paidAt,
        ?string $voidedAt = null): int
    {
        return (int) DB::table('payments')->insertGetId([
            'branch_id' => $branch->id, 'customer_id' => $customer->id, 'rental_id' => $rentalId,
            'payment_method_id' => $method->id, 'payment_number' => $number,
            'direction' => 'in', 'type' => $type, 'status' => $status,
            'amount' => $amount, 'paid_at' => $paidAt, 'voided_at' => $voidedAt,
            'created_at' => $paidAt, 'updated_at' => $voidedAt ?? $paidAt,
        ]);
    }

    private function refund(int $paymentId, Branch $branch, PaymentMethod $method,
        string $number, float $amount, string $processedAt): void
    {
        DB::table('refunds')->insert([
            'branch_id' => $branch->id, 'payment_id' => $paymentId,
            'payment_method_id' => $method->id, 'refund_number' => $number,
            'amount' => $amount, 'status' => 'paid', 'reason' => 'Historical test',
            'processed_at' => $processedAt, 'created_at' => $processedAt, 'updated_at' => $processedAt,
        ]);
    }

    private function return(int $rentalId, Branch $branch, string $returnedAt, float $charge): void
    {
        DB::table('rental_returns')->insert([
            'branch_id' => $branch->id, 'rental_id' => $rentalId,
            'return_number' => 'RET-ASOF-'.$rentalId, 'type' => 'final', 'status' => 'completed',
            'returned_at' => $returnedAt, 'total_charge_amount' => $charge,
            'created_at' => $returnedAt, 'updated_at' => $returnedAt,
        ]);
    }
}
