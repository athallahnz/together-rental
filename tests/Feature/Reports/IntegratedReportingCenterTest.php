<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class IntegratedReportingCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_center_summarizes_operational_and_financial_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager, $branch, $customer] = $this->fixture('branch-manager');
        $rental = $this->rental($branch, $customer, 500000, 150000, 'PNG-RNT-REPORT-001');
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();
        $this->payment($branch, $customer, $method, 350000, 'in', 'completed', 'PNG-PAY-REPORT-001');

        $this->actingAs($manager)
            ->get(route('reports.index', [
                'from' => '2026-08-01',
                'to' => '2026-08-09',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reports/index')
                ->where('filters.report', 'operational')
                ->where('filters.branch_id', $branch->id)
                ->where('summary.0.value', 1)
                ->where('summary.1.value', 500000)
                ->where('summary.2.value', 350000)
                ->where('summary.4.value', 150000)
                ->where('reportMeta.row_count', 1)
                ->where('rows.data.0.href', '/rentals/'.$rental->id)
                ->has('trend', 9)
                ->has('branchPerformance', 1)
                ->has('tabs', 7));
    }

    public function test_finance_report_preserves_void_and_refund_cash_flow_invariants(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager, $branch, $customer] = $this->fixture('branch-manager');
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();
        $completed = $this->payment($branch, $customer, $method, 500000, 'in', 'completed', 'PNG-PAY-REPORT-IN');
        $this->payment($branch, $customer, $method, 300000, 'in', 'void', 'PNG-PAY-REPORT-VOID');
        $this->payment($branch, $customer, $method, 100000, 'out', 'completed', 'PNG-PAY-REPORT-OUT');
        $this->refund($branch, $completed, $method, 50000, 'paid', 'PNG-RFD-REPORT-PAID');
        $this->refund($branch, $completed, $method, 70000, 'requested', 'PNG-RFD-REPORT-REQUESTED');

        $this->actingAs($manager)
            ->get(route('reports.index', [
                'report' => 'finance',
                'from' => '2026-08-01',
                'to' => '2026-08-09',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.2.value', 500000)
                ->where('summary.3.value', 350000)
                ->where('summary.5.value', 50000)
                ->where('reportMeta.row_count', 5)
                ->has('rows.data', 5));
    }

    public function test_branch_scoped_user_is_isolated_and_company_role_can_consolidate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager, $branch, $customer] = $this->fixture('branch-manager');
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'city' => 'Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $owner = $this->userForRole('owner-management', $branch, null);
        $otherCustomer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $other->id,
            'customer_number' => 'MDN-CUS-REPORT',
            'name' => 'Pelanggan Madiun',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);
        $this->rental($branch, $customer, 100000, 0, 'PNG-RNT-SCOPE');
        $this->rental($other, $otherCustomer, 200000, 0, 'MDN-RNT-SCOPE');

        $this->actingAs($manager)
            ->get(route('reports.index', ['from' => '2026-08-01', 'to' => '2026-08-09']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', $branch->id)
                ->where('summary.0.value', 1)
                ->where('summary.1.value', 100000));
        $this->actingAs($manager)
            ->get(route('reports.index', ['branch_id' => $other->id]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('reports.index', ['from' => '2026-08-01', 'to' => '2026-08-09']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', null)
                ->where('summary.0.value', 2)
                ->where('summary.1.value', 300000)
                ->has('branchPerformance', 2));
    }

    public function test_receivable_report_is_an_as_of_position_and_excludes_zero_balances(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager, $branch, $customer] = $this->fixture('branch-manager');
        $this->rental($branch, $customer, 400000, 125000, 'PNG-RNT-RECEIVABLE');
        $this->rental($branch, $customer, 200000, 0, 'PNG-RNT-SETTLED');

        $this->actingAs($manager)
            ->get(route('reports.index', [
                'report' => 'receivables',
                'from' => '2026-08-01',
                'to' => '2026-08-09',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('reportMeta.row_count', 1)
                ->where('rows.data.0.values.balance_due', 125000)
                ->where('rows.data.0.values.days_overdue', 1));
    }

    public function test_cash_asset_maintenance_and_transfer_reports_are_available(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager, $branch] = $this->fixture('branch-manager');
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $registerId = (int) DB::table('cash_registers')->where('branch_id', $branch->id)->value('id');
        $sessionId = DB::table('cash_sessions')->insertGetId([
            'cash_register_id' => $registerId,
            'opened_by' => $manager->id,
            'status' => 'closed',
            'opened_at' => now()->subHours(8),
            'closed_at' => now(),
            'opening_balance' => 100000,
            'expected_closing_balance' => 250000,
            'actual_closing_balance' => 245000,
            'difference_amount' => -5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cash_transactions')->insert([
            'cash_session_id' => $sessionId,
            'transaction_number' => 'CASH-REPORT-001',
            'direction' => 'in',
            'type' => 'payment',
            'amount' => 150000,
            'balance_after' => 250000,
            'occurred_at' => now(),
            'created_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'company_id' => $branch->company_id,
            'sku' => 'CAM-REPORT',
            'name' => 'Sony A7 III Report',
            'tracking_type' => 'serialized',
            'replacement_value' => 25000000,
            'is_rentable' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assetId = DB::table('assets')->insertGetId([
            'product_id' => $productId,
            'owning_branch_id' => $branch->id,
            'current_branch_id' => $branch->id,
            'asset_code' => 'PNG-AST-REPORT',
            'status' => 'maintenance',
            'condition' => 'fair',
            'purchase_price' => 18000000,
            'replacement_value' => 25000000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('maintenance_orders')->insert([
            'branch_id' => $branch->id,
            'asset_id' => $assetId,
            'maintenance_number' => 'PNG-MTN-REPORT',
            'type' => 'corrective',
            'status' => 'completed',
            'problem_description' => 'Uji laporan maintenance.',
            'actual_cost' => 350000,
            'reported_at' => now()->subDay(),
            'completed_at' => now(),
            'created_by' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('branch_transfers')->insert([
            'company_id' => $branch->company_id,
            'from_branch_id' => $branch->id,
            'to_branch_id' => $other->id,
            'transfer_number' => 'PNG-TRF-REPORT',
            'status' => 'in_transit',
            'revision_number' => 1,
            'lock_version' => 0,
            'requested_by' => $manager->id,
            'shipped_by' => $manager->id,
            'requested_at' => now()->subDay(),
            'shipped_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['cash', 'assets', 'transfers'] as $report) {
            $this->actingAs($manager)
                ->get(route('reports.index', [
                    'report' => $report,
                    'from' => '2026-08-01',
                    'to' => '2026-08-09',
                ]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->where('reportMeta.key', $report)
                    ->where('reportMeta.row_count', 1)
                    ->has('rows.data', 1));
        }
    }

    public function test_excel_and_pdf_exports_are_valid_downloads(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager] = $this->fixture('branch-manager');
        $query = [
            'report' => 'operational',
            'from' => '2026-08-01',
            'to' => '2026-08-09',
        ];

        $excel = $this->actingAs($manager)->get(route('reports.export', [...$query, 'format' => 'excel']));
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');
        $this->assertStringStartsWith('<?xml version="1.0"', $excel->getContent());
        $this->assertStringContainsString('Excel.Sheet', $excel->getContent());

        $pdf = $this->actingAs($manager)->get(route('reports.export', [...$query, 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
        $this->assertStringEndsWith('%%EOF', $pdf->getContent());
    }

    public function test_view_and_export_permissions_remain_separate(): void
    {
        [, $branch] = $this->fixture('branch-manager');
        $cashier = $this->userForRole('cashier', $branch, $branch->id);
        $operator = $this->userForRole('rental-operator', $branch, $branch->id);

        $this->actingAs($cashier)->get(route('reports.index'))->assertOk();
        $this->actingAs($cashier)
            ->get(route('reports.export', ['format' => 'pdf']))
            ->assertForbidden();
        $this->actingAs($operator)->get(route('reports.index'))->assertForbidden();
    }

    /** @return array{User, Branch, Customer} */
    private function fixture(string $roleSlug): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = $this->userForRole($roleSlug, $branch, $branch->id);
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-REPORT',
            'name' => 'Pelanggan Reporting Center',
            'phone' => '081234567890',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);

        return [$user, $branch, $customer];
    }

    private function userForRole(string $roleSlug, Branch $branch, ?int $roleBranchId): User
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
            Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            ['branch_id' => $roleBranchId, 'assigned_at' => now()],
        );

        return $user;
    }

    private function rental(
        Branch $branch,
        Customer $customer,
        float $total,
        float $balance,
        string $number,
    ): Rental {
        return Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => $number,
            'status' => 'active',
            'checked_out_at' => now()->subDays(2),
            'due_at' => now()->subDay(),
            'total_amount' => $total,
            'paid_amount' => $total - $balance,
            'balance_due' => $balance,
            'deposit_amount' => 100000,
            'created_at' => now()->subDays(2),
            'updated_at' => now(),
        ]);
    }

    private function payment(
        Branch $branch,
        Customer $customer,
        PaymentMethod $method,
        float $amount,
        string $direction,
        string $status,
        string $number,
    ): Payment {
        return Payment::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'payment_number' => $number,
            'direction' => $direction,
            'type' => $direction === 'in' ? 'rental' : 'expense',
            'source_context' => $direction === 'in' ? 'rental_checkout' : null,
            'status' => $status,
            'amount' => $amount,
            'paid_at' => now(),
            'external_reference' => 'REF-'.$number,
        ]);
    }

    private function refund(
        Branch $branch,
        Payment $payment,
        PaymentMethod $method,
        float $amount,
        string $status,
        string $number,
    ): Refund {
        return Refund::query()->create([
            'branch_id' => $branch->id,
            'payment_id' => $payment->id,
            'payment_method_id' => $method->id,
            'refund_number' => $number,
            'refund_type' => 'partial',
            'amount' => $amount,
            'status' => $status,
            'reason' => 'Uji Integrated Reporting Center.',
            'requested_by' => $payment->received_by,
            'processed_at' => $status === 'paid' ? now() : null,
        ]);
    }
}
