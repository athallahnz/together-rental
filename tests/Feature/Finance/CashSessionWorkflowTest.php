<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\CashLedger;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashSessionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_session_uses_ledger_for_expected_closing_and_variance(): void
    {
        [$user, $branch, $register] = $this->financeFixture();

        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('finance.cash-sessions.store', $register), [
                'opening_balance' => 100000,
                'opening_notes' => 'Modal awal shift pagi.',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();

        $session = CashSession::query()->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $payment = Payment::query()->create([
            'branch_id' => $branch->id,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
            'payment_number' => 'PNG-PAY-TEST-001',
            'direction' => 'in',
            'type' => 'rental',
            'status' => 'completed',
            'amount' => 50000,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        $ledger = app(CashLedger::class);
        $ledger->recordPayment($payment, $user);
        $ledger->recordPayment($payment, $user);

        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_session_id' => $session->id,
            'direction' => 'in',
            'amount' => 50000,
            'balance_after' => 150000,
        ]);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('finance.cash-sessions.close', $session), [
                'actual_closing_balance' => 149000,
                'closing_notes' => 'Selisih diperiksa supervisor.',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertSame('150000.00', $session->expected_closing_balance);
        $this->assertSame('149000.00', $session->actual_closing_balance);
        $this->assertSame('-1000.00', $session->difference_amount);
    }

    public function test_register_cannot_have_two_open_sessions(): void
    {
        [$user, , $register] = $this->financeFixture();

        $payload = ['opening_balance' => 0];
        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('finance.cash-sessions.store', $register), $payload)
            ->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->from('/dashboard')
            ->post(route('finance.cash-sessions.store', $register), $payload)
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors('cash_register');

        $this->assertDatabaseCount('cash_sessions', 1);
    }

    /** @return array{0: User, 1: Branch, 2: CashRegister} */
    private function financeFixture(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
        ]);
        $roleId = (int) DB::table('roles')
            ->where('company_id', $branch->company_id)->where('slug', 'super-admin')->value('id');
        $now = now();

        DB::table('branch_user')->insert([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('role_user')->insert([
            'role_id' => $roleId, 'user_id' => $user->id, 'branch_id' => null,
            'assigned_by' => null, 'assigned_at' => $now, 'expires_at' => null,
        ]);

        return [$user, $branch, CashRegister::query()->where('branch_id', $branch->id)->firstOrFail()];
    }
}
