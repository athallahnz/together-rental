<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\PaymentManager;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\BranchTransferExpense;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class PaymentCenterTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_payment_center_lists_accessible_payments_and_calculates_filtered_kpis(): void
    {
        [$user, $branch, $customer] = $this->fixture('branch-manager');
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $qris = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();
        $session = $this->openCashSession($user, $branch, 100000);
        $manager = app(PaymentManager::class);

        $cashPayment = $manager->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $cash->id,
            'cash_session_id' => $session->id,
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'booking',
            'amount' => 150000,
        ], $user);
        $activeQris = $manager->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $qris->id,
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'booking',
            'amount' => 250000,
            'external_reference' => 'QRIS-ACTIVE-001',
        ], $user);
        $voidedQris = $manager->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $qris->id,
            'direction' => 'in',
            'type' => 'deposit',
            'source_context' => 'booking',
            'amount' => 50000,
            'external_reference' => 'QRIS-VOID-001',
        ], $user);
        $manager->void($voidedQris, 'Duplikasi payment pada pengujian Payment Center.', $user);

        $this->actingAs($user)
            ->get(route('finance.payments.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/payments/index')
                ->has('payments.data', 3)
                ->where('summary.total_count', 3)
                ->where('summary.gross_amount', 450000)
                ->where('summary.cash_amount', 150000)
                ->where('summary.non_cash_amount', 250000)
                ->where('summary.void_count', 1)
                ->where('summary.void_amount', 50000)
                ->where('summary.net_amount', 400000));

        $this->actingAs($user)
            ->get(route('finance.payments.index', [
                'payment_method_id' => $qris->id,
                'status' => 'completed',
                'search' => $activeQris->payment_number,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('payments.data', 1)
                ->where('payments.data.0.id', $activeQris->id)
                ->where('summary.total_count', 1)
                ->where('summary.net_amount', 250000));

        $this->assertDatabaseHas('cash_transactions', [
            'payment_id' => $cashPayment->id,
            'direction' => 'in',
            'amount' => 150000,
        ]);
    }

    public function test_payment_detail_exposes_source_ledger_audit_and_void_eligibility(): void
    {
        [$user, $branch, $customer] = $this->fixture('branch-manager');
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch);
        $payment = app(PaymentManager::class)->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $cash->id,
            'cash_session_id' => $session->id,
            'direction' => 'in',
            'type' => 'deposit',
            'source_context' => 'booking',
            'amount' => 200000,
        ], $user);

        $this->actingAs($user)
            ->get(route('finance.payments.show', $payment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/payments/show')
                ->where('payment.id', $payment->id)
                ->where('payment.customer.id', $customer->id)
                ->has('payment.cash_transactions', 1)
                ->where('voidEligibility.allowed', true)
                ->where('permissions.void', true));

        $session->update(['status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($user)
            ->get(route('finance.payments.show', $payment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('voidEligibility.allowed', false)
                ->where('voidEligibility.field', 'cash_session_id')
                ->where('permissions.void', false));
    }

    public function test_void_from_payment_center_appends_reversal_and_audit_without_deleting_payment(): void
    {
        [$user, $branch, $customer] = $this->fixture('branch-manager');
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch, 50000);
        $payment = app(PaymentManager::class)->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $cash->id,
            'cash_session_id' => $session->id,
            'direction' => 'in',
            'type' => 'deposit',
            'source_context' => 'booking',
            'amount' => 200000,
        ], $user);

        $this->actingAs($user)
            ->from(route('finance.payments.show', $payment))
            ->post(route('finance.payments.void', $payment), [
                'reason' => 'Nominal deposit salah input dan harus dibalik.',
            ])
            ->assertRedirect(route('finance.payments.show', $payment))
            ->assertSessionHasNoErrors();

        $this->assertSame('void', $payment->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('cash_transactions', [
            'payment_id' => $payment->id,
            'direction' => 'out',
            'type' => 'deposit_void',
            'amount' => 200000,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Payment::class,
            'subject_id' => $payment->id,
            'actor_id' => $user->id,
            'event' => 'payment.voided',
        ]);
    }

    public function test_generic_void_keeps_transfer_expense_and_payment_status_consistent(): void
    {
        [$user, $branch] = $this->fixture('branch-manager');
        $destination = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $transfer = BranchTransfer::query()->create([
            'company_id' => $branch->company_id,
            'from_branch_id' => $branch->id,
            'to_branch_id' => $destination->id,
            'transfer_number' => 'PNG-TRF-PAYMENT-CENTER-001',
            'status' => 'draft',
        ]);
        $payment = app(PaymentManager::class)->record($branch, [
            'payment_method_id' => $method->id,
            'direction' => 'out',
            'type' => 'transfer_expense',
            'source_context' => 'transfer_expense',
            'amount' => 75000,
            'external_reference' => 'BANK-TRANSFER-EXP-001',
        ], $user);
        $expense = BranchTransferExpense::query()->create([
            'branch_transfer_id' => $transfer->id,
            'expense_branch_id' => $branch->id,
            'payment_method_id' => $method->id,
            'payment_id' => $payment->id,
            'expense_type' => 'shipping',
            'status' => 'paid',
            'actual_amount' => 75000,
            'paid_at' => now(),
            'paid_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('finance.payments.void', $payment), [
                'reason' => 'Biaya transfer tercatat dua kali oleh kasir.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('void', $payment->fresh()->status);
        $this->assertSame('void', $expense->fresh()->status);
        $this->assertSame($user->id, $expense->fresh()->voided_by);
        $this->assertSame(
            'Biaya transfer tercatat dua kali oleh kasir.',
            $expense->fresh()->void_reason,
        );
    }

    public function test_branch_user_cannot_view_or_void_foreign_payment(): void
    {
        [$user, $branch] = $this->fixture('branch-manager');
        $foreignBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'SBY',
            'name' => 'Together Kamera Surabaya',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $payment = Payment::query()->create([
            'branch_id' => $foreignBranch->id,
            'payment_method_id' => $method->id,
            'payment_number' => 'SBY-PAY-000001',
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'booking',
            'status' => 'completed',
            'amount' => 100000,
            'paid_at' => now(),
            'external_reference' => 'FOREIGN-PAYMENT',
        ]);

        $this->actingAs($user)
            ->get(route('finance.payments.show', $payment))
            ->assertNotFound();
        $this->actingAs($user)
            ->post(route('finance.payments.void', $payment), [
                'reason' => 'Tidak boleh membatalkan payment cabang lain.',
            ])
            ->assertNotFound();
        $this->actingAs($user)
            ->get(route('finance.payments.index', [
                'branch_id' => $foreignBranch->id,
            ]))
            ->assertForbidden();
    }

    public function test_user_without_payment_permission_cannot_open_payment_center(): void
    {
        [$user] = $this->fixture('inventory-staff');

        $this->actingAs($user)
            ->get(route('finance.payments.index'))
            ->assertForbidden();
    }

    /** @return array{User, Branch, Customer} */
    private function fixture(string $roleSlug): array
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
            Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
            ['branch_id' => $branch->id, 'assigned_at' => now()],
        );
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-PAYMENT-CENTER-001',
            'name' => 'Pelanggan Payment Center',
            'phone' => '081234567890',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);

        return [$user, $branch, $customer];
    }
}
