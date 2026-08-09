<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\PaymentManager;
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
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_calculates_cash_flow_current_state_and_attention_without_counting_voids(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$user, $branch, $customer] = $this->fixture('branch-manager');
        $qris = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();
        $transfer = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $manager = app(PaymentManager::class);

        $manager->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $qris->id,
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'booking',
            'amount' => 300000,
            'external_reference' => 'QRIS-RENTAL-DASHBOARD',
            'paid_at' => now(),
        ], $user);
        $deposit = $manager->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $qris->id,
            'direction' => 'in',
            'type' => 'deposit',
            'source_context' => 'booking',
            'amount' => 100000,
            'external_reference' => 'QRIS-DEPOSIT-DASHBOARD',
            'paid_at' => now(),
        ], $user);
        $manager->record($branch, [
            'payment_method_id' => $transfer->id,
            'direction' => 'out',
            'type' => 'transfer_expense',
            'source_context' => 'transfer_expense',
            'amount' => 50000,
            'external_reference' => 'TRANSFER-EXPENSE-DASHBOARD',
            'paid_at' => now(),
        ], $user);
        $voided = $manager->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $qris->id,
            'direction' => 'in',
            'type' => 'deposit',
            'source_context' => 'booking',
            'amount' => 70000,
            'external_reference' => 'QRIS-VOID-DASHBOARD',
            'paid_at' => now(),
        ], $user);
        $manager->void($voided, 'Payment uji dashboard dibatalkan.', $user);

        Refund::query()->create([
            'branch_id' => $branch->id,
            'payment_id' => $deposit->id,
            'payment_method_id' => $qris->id,
            'refund_number' => 'PNG-RFD-DASHBOARD-PAID',
            'refund_type' => 'partial',
            'amount' => 60000,
            'status' => 'paid',
            'reason' => 'Pengembalian sebagian deposit.',
            'requested_by' => $user->id,
            'processed_by' => $user->id,
            'processed_at' => now(),
        ]);
        Refund::query()->create([
            'branch_id' => $branch->id,
            'payment_id' => $deposit->id,
            'payment_method_id' => $qris->id,
            'refund_number' => 'PNG-RFD-DASHBOARD-REQUESTED',
            'refund_type' => 'partial',
            'amount' => 20000,
            'status' => 'requested',
            'reason' => 'Menunggu keputusan pengembalian deposit.',
            'requested_by' => $user->id,
        ]);
        Rental::query()->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'rental_number' => 'PNG-RNT-DASHBOARD-001',
            'status' => 'active',
            'checked_out_at' => now()->subDays(3),
            'due_at' => now()->subDay(),
            'total_amount' => 500000,
            'paid_amount' => 350000,
            'balance_due' => 150000,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('finance.dashboard', [
                'from' => '2026-08-01',
                'to' => '2026-08-09',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/dashboard')
                ->where('filters.branch_id', $branch->id)
                ->where('summary.gross_collections', 400000)
                ->where('summary.rental_collections', 300000)
                ->where('summary.deposit_collections', 100000)
                ->where('summary.operating_outflows', 50000)
                ->where('summary.paid_refunds', 60000)
                ->where('summary.net_cash_flow', 290000)
                ->where('summary.completed_payment_count', 3)
                ->where('summary.inbound_payment_count', 2)
                ->where('summary.void_count', 1)
                ->where('summary.void_amount', 70000)
                ->where('summary.deposit_held', 40000)
                ->where('summary.outstanding_refund_count', 1)
                ->where('summary.outstanding_refund_amount', 20000)
                ->where('summary.receivable_count', 1)
                ->where('summary.receivable_amount', 150000)
                ->where('attention.requested_refunds.count', 1)
                ->where('attention.overdue_receivables.count', 1)
                ->has('trend', 9)
                ->has('paymentMethods', 1)
                ->where('paymentMethods.0.id', $qris->id)
                ->where('paymentMethods.0.collections', 400000)
                ->where('paymentMethods.0.refunds', 60000)
                ->where('paymentMethods.0.net', 340000)
                ->has('sourceContexts', 2)
                ->has('recentActivity', 6));
    }

    public function test_dashboard_compares_with_an_equal_previous_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$user, $branch] = $this->fixture('branch-manager');
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();

        $this->payment($branch, $method, 100000, '2026-08-04 10:00:00', 'PREVIOUS');
        $this->payment($branch, $method, 200000, '2026-08-08 10:00:00', 'CURRENT');

        $this->actingAs($user)
            ->get(route('finance.dashboard', [
                'from' => '2026-08-07',
                'to' => '2026-08-09',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.gross_collections', 200000)
                ->where('comparison.from', '2026-08-04')
                ->where('comparison.to', '2026-08-06')
                ->where('comparison.gross_collections_percent', 100)
                ->where('comparison.rental_collections_percent', 100)
                ->has('trend', 3));
    }

    public function test_company_role_sees_accessible_branches_while_branch_role_is_isolated(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-09 12:00:00'));
        [$manager, $branch] = $this->fixture('branch-manager');
        $owner = $this->userForRole('owner-management', $branch, null);
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();
        $this->payment($branch, $method, 100000, now()->toDateTimeString(), 'PNG');
        $this->payment($other, $method, 200000, now()->toDateTimeString(), 'MDN');

        $this->actingAs($manager)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', $branch->id)
                ->where('summary.gross_collections', 100000)
                ->has('branchPerformance', 1));
        $this->actingAs($manager)
            ->get(route('finance.dashboard', ['branch_id' => $other->id]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.branch_id', null)
                ->where('summary.gross_collections', 300000)
                ->has('branchPerformance', 2));
    }

    public function test_dashboard_permission_allows_cashier_and_rejects_inventory_staff(): void
    {
        [, $branch] = $this->fixture('branch-manager');
        $cashier = $this->userForRole('cashier', $branch, $branch->id);
        $inventory = $this->userForRole('inventory-staff', $branch, $branch->id);

        $this->actingAs($cashier)
            ->get(route('finance.dashboard'))
            ->assertOk();
        $this->actingAs($inventory)
            ->get(route('finance.dashboard'))
            ->assertForbidden();
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
            'customer_number' => 'PNG-CUS-FINANCE-DASHBOARD',
            'name' => 'Pelanggan Finance Dashboard',
            'phone' => '081234567890',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);

        return [$user, $branch, $customer];
    }

    private function userForRole(
        string $roleSlug,
        Branch $branch,
        ?int $roleBranchId,
    ): User {
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

    private function payment(
        Branch $branch,
        PaymentMethod $method,
        float $amount,
        string $paidAt,
        string $reference,
    ): Payment {
        return Payment::query()->create([
            'branch_id' => $branch->id,
            'payment_method_id' => $method->id,
            'payment_number' => "{$branch->code}-PAY-DASHBOARD-{$reference}",
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'booking',
            'status' => 'completed',
            'amount' => $amount,
            'paid_at' => $paidAt,
            'external_reference' => "REF-{$reference}",
        ]);
    }
}
