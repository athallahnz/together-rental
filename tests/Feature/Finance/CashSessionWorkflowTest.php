<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\CashLedger;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_closed_cash_session_history_and_detail_are_read_only(): void
    {
        [$user, , $register] = $this->financeFixture();

        $this->actingAs($user)
            ->post(route('finance.cash-sessions.store', $register), [
                'opening_balance' => 5000000,
                'opening_notes' => 'Modal awal UAT histori kas.',
            ])
            ->assertSessionHasNoErrors();

        $session = CashSession::query()->firstOrFail();
        CashTransaction::query()->create([
            'cash_session_id' => $session->id,
            'transaction_number' => 'CASH-HISTORY-IN-001',
            'direction' => 'in',
            'type' => 'payment',
            'amount' => 250000,
            'balance_after' => 5250000,
            'occurred_at' => now()->addMinute(),
            'description' => 'Kas masuk pengujian.',
            'created_by' => $user->id,
        ]);
        CashTransaction::query()->create([
            'cash_session_id' => $session->id,
            'transaction_number' => 'CASH-HISTORY-OUT-001',
            'direction' => 'out',
            'type' => 'refund',
            'amount' => 250000,
            'balance_after' => 5000000,
            'occurred_at' => now()->addMinutes(2),
            'description' => 'Kas keluar pengujian.',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('finance.cash-sessions.close', $session), [
                'actual_closing_balance' => 4990000,
                'closing_notes' => 'Selisih minus sepuluh ribu.',
            ])
            ->assertSessionHasNoErrors();

        $session->refresh();
        $this->assertSame('5000000.00', $session->expected_closing_balance);
        $this->assertSame('4990000.00', $session->actual_closing_balance);
        $this->assertSame('-10000.00', $session->difference_amount);
        $transactionCount = CashTransaction::query()->where('cash_session_id', $session->id)->count();

        $this->actingAs($user)
            ->get(route('finance.cash-sessions.index', $register))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/cash-sessions/index')
                ->where('cashRegister.id', $register->id)
                ->has('sessions.data', 1)
                ->where('sessions.data.0.id', $session->id)
                ->where('sessions.data.0.incoming_total', fn ($value): bool => is_numeric($value) && (float) $value === 250000.0)
                ->where('sessions.data.0.outgoing_total', fn ($value): bool => is_numeric($value) && (float) $value === 250000.0)
                ->where('sessions.data.0.expected_closing_balance', '5000000.00')
                ->where('sessions.data.0.actual_closing_balance', '4990000.00')
                ->where('sessions.data.0.difference_amount', '-10000.00')
                ->where('sessions.data.0.closing_notes', 'Selisih minus sepuluh ribu.'));

        $this->actingAs($user)
            ->get(route('finance.cash-sessions.show', $session))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/cash-sessions/show')
                ->where('cashRegister.id', $register->id)
                ->where('session.id', $session->id)
                ->where('session.opening_balance', '5000000.00')
                ->where('session.incoming_total', fn ($value): bool => is_numeric($value) && (float) $value === 250000.0)
                ->where('session.outgoing_total', fn ($value): bool => is_numeric($value) && (float) $value === 250000.0)
                ->where('session.ledger_expected_balance', fn ($value): bool => is_numeric($value) && (float) $value === 5000000.0)
                ->where('session.expected_closing_balance', '5000000.00')
                ->where('session.actual_closing_balance', '4990000.00')
                ->where('session.difference_amount', '-10000.00')
                ->where('session.closing_notes', 'Selisih minus sepuluh ribu.')
                ->has('transactions', 2));

        $this->assertSame($transactionCount, CashTransaction::query()->where('cash_session_id', $session->id)->count());
        $this->assertSame('closed', $session->fresh()->status);
    }

    public function test_cash_session_history_is_branch_isolated(): void
    {
        [$user, $branch] = $this->financeFixture('branch-manager');
        $otherBranch = Branch::query()->firstOrCreate(
            ['company_id' => $branch->company_id, 'code' => 'MDN'],
            [
                'name' => 'Together Kamera Madiun',
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        );
        $otherRegister = CashRegister::query()->firstOrCreate(
            ['branch_id' => $otherBranch->id, 'code' => 'MAIN'],
            ['name' => 'Kasir Utama MDN', 'is_active' => true],
        );
        $otherSession = CashSession::query()->create([
            'cash_register_id' => $otherRegister->id,
            'opened_by' => $user->id,
            'closed_by' => $user->id,
            'status' => 'closed',
            'opened_at' => now()->subHour(),
            'closed_at' => now(),
            'opening_balance' => 100000,
            'expected_closing_balance' => 100000,
            'actual_closing_balance' => 100000,
            'difference_amount' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('finance.cash-sessions.index', $otherRegister))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('finance.cash-sessions.show', $otherSession))
            ->assertNotFound();
    }

    /** @return array{0: User, 1: Branch, 2: CashRegister} */
    private function financeFixture(string $roleSlug = 'super-admin'): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = User::factory()->create([
            'company_id' => $branch->company_id,
            'current_branch_id' => $branch->id,
        ]);
        $role = DB::table('roles')
            ->where('company_id', $branch->company_id)
            ->where('slug', $roleSlug)
            ->firstOrFail();
        $now = now();

        DB::table('branch_user')->insert([
            'branch_id' => $branch->id, 'user_id' => $user->id,
            'is_default' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('role_user')->insert([
            'role_id' => $role->id, 'user_id' => $user->id,
            'branch_id' => $role->scope === 'branch' ? $branch->id : null,
            'assigned_by' => null, 'assigned_at' => $now, 'expires_at' => null,
        ]);

        return [$user, $branch, CashRegister::query()->where('branch_id', $branch->id)->firstOrFail()];
    }
}
