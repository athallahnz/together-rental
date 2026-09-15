<?php

namespace Tests\Feature\Finance;

use App\Domain\Operations\OperationalDataResetService;
use App\Models\Branch;
use App\Models\FinancialCategory;
use App\Models\OperationalExpense;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class OperationalExpenseCenterTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_recorded_expense_does_not_touch_payment_or_cash_ledger(): void
    {
        [$user, $branch, $category] = $this->fixture();
        Storage::fake('local');

        $this->actingAs($user)
            ->post(route('finance.expenses.store'), [
                'branch_id' => $branch->id,
                'financial_category_id' => $category->id,
                'amount' => 125000,
                'incurred_at' => now()->format('Y-m-d H:i:s'),
                'vendor_name' => 'Toko ATK Ponorogo',
                'external_reference' => 'INV-ATK-001',
                'notes' => 'Pembelian kebutuhan operasional kantor.',
                'proof' => UploadedFile::fake()->image('nota.jpg'),
            ])
            ->assertRedirect(route('finance.expenses.index'))
            ->assertSessionHasNoErrors();

        $expense = OperationalExpense::query()->firstOrFail();
        $this->assertSame('recorded', $expense->status);
        $this->assertSame('125000.00', $expense->amount);
        $this->assertNull($expense->payment_id);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('cash_transactions', 0);
        Storage::disk('local')->assertExists((string) $expense->proof_path);
        $this->actingAs($user)
            ->get(route('finance.expenses.proof', $expense))
            ->assertOk();
        $this->actingAs($user)
            ->get(route('finance.expenses.show', $expense))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/operational-expenses/show')
                ->where('hasProof', true)
                ->missing('expense.proof_path'));
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => OperationalExpense::class,
            'subject_id' => $expense->id,
            'event' => 'operational-expense.created',
        ]);
    }

    public function test_cash_payment_creates_one_outgoing_payment_and_one_cash_ledger_row(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $expense = $this->expense($user, $branch, $category, 80000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch, 200000);
        $payload = [
            'payment_method_id' => $cash->id,
            'cash_session_id' => $session->id,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'payment_reference' => '',
            'notes' => 'Dibayar dari petty cash shift pagi.',
        ];

        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), $payload)
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), $payload)
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('paid', $expense->status);
        $this->assertNotNull($expense->payment_id);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('payments', [
            'id' => $expense->payment_id,
            'branch_id' => $branch->id,
            'financial_category_id' => $category->id,
            'direction' => 'out',
            'type' => 'operational_expense',
            'source_context' => 'operational_expense',
            'status' => 'completed',
            'amount' => 80000,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_session_id' => $session->id,
            'payment_id' => $expense->payment_id,
            'direction' => 'out',
            'type' => 'operational_expense',
            'amount' => 80000,
            'balance_after' => 120000,
        ]);
    }

    public function test_non_cash_expense_requires_reference_and_never_writes_cash_ledger(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $expense = $this->expense($user, $branch, $category, 95000);
        $transfer = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), [
                'payment_method_id' => $transfer->id,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'payment_reference' => '',
            ])
            ->assertSessionHasErrors('payment_reference');

        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), [
                'payment_method_id' => $transfer->id,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'payment_reference' => 'TRF-OPS-20260811-001',
            ])
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('paid', $expense->status);
        $this->assertDatabaseHas('payments', [
            'id' => $expense->payment_id,
            'direction' => 'out',
            'external_reference' => 'TRF-OPS-20260811-001',
        ]);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_paid_cash_expense_void_is_append_only_and_closed_session_blocks_void(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $expense = $this->expense($user, $branch, $category, 50000);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch, 100000);

        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), [
                'payment_method_id' => $cash->id,
                'cash_session_id' => $session->id,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('finance.expenses.void', $expense), [
                'reason' => 'Nota dibatalkan vendor dan pengeluaran tidak jadi.',
            ])
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('void', $expense->status);
        $this->assertDatabaseHas('payments', ['id' => $expense->payment_id, 'status' => 'void']);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 2);
        $this->assertDatabaseHas('cash_transactions', [
            'payment_id' => $expense->payment_id,
            'direction' => 'in',
            'type' => 'operational_expense_void',
            'balance_after' => 100000,
        ]);

        $session->update(['status' => 'closed', 'closed_at' => now()]);
        $second = $this->expense($user, $branch, $category, 30000, 'EXPENSE-SECOND');
        $secondSession = $this->openCashSession($user, $branch, 50000);
        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $second), [
                'payment_method_id' => $cash->id,
                'cash_session_id' => $secondSession->id,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();
        $secondSession->update(['status' => 'closed', 'closed_at' => now()]);

        $this->actingAs($user)
            ->post(route('finance.expenses.void', $second), [
                'reason' => 'Mencoba void setelah sesi kas sudah ditutup.',
            ])
            ->assertSessionHasErrors('cash_session_id');
        $this->assertSame('paid', $second->fresh()->status);
    }

    public function test_branch_isolation_and_private_proof_are_enforced(): void
    {
        [$manager, $branch, $category] = $this->fixture();
        Storage::fake('local');
        $other = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $otherManager = $this->userForRole('branch-manager', $other);
        $otherCategory = FinancialCategory::query()
            ->where('company_id', $other->company_id)
            ->where('code', $category->code)
            ->firstOrFail();

        $this->actingAs($otherManager)
            ->post(route('finance.expenses.store'), [
                'branch_id' => $other->id,
                'financial_category_id' => $otherCategory->id,
                'amount' => 70000,
                'incurred_at' => now()->format('Y-m-d H:i:s'),
                'vendor_name' => 'Vendor Madiun',
                'proof' => UploadedFile::fake()->image('mdn-proof.jpg'),
            ])
            ->assertSessionHasNoErrors();
        $foreign = OperationalExpense::query()->where('branch_id', $other->id)->firstOrFail();

        $this->actingAs($manager)
            ->get(route('finance.expenses.show', $foreign))
            ->assertNotFound();
        $this->actingAs($manager)
            ->get(route('finance.expenses.proof', $foreign))
            ->assertNotFound();
        $this->actingAs($manager)
            ->get(route('finance.expenses.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/operational-expenses/index')
                ->where('expenses.total', 0));
    }

    public function test_finance_dashboard_and_payment_center_include_paid_operational_expense(): void
    {
        [$user, $branch, $category] = $this->fixture();
        $expense = $this->expense($user, $branch, $category, 65000);
        $transfer = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), [
                'payment_method_id' => $transfer->id,
                'paid_at' => now()->format('Y-m-d H:i:s'),
                'payment_reference' => 'BANK-OPS-65000',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('summary.operating_outflows', 65000)
                ->where('summary.net_cash_flow', -65000)
                ->where('sourceContexts.0.context', 'operational_expense'));

        $this->actingAs($user)
            ->get(route('finance.payments.index', ['source_context' => 'operational_expense']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('payments.total', 1)
                ->where('payments.data.0.operational_expense.id', $expense->id));
    }

    public function test_operational_reset_removes_expense_payment_cash_and_private_proof(): void
    {
        [$user, $branch, $category] = $this->fixture('super-admin');
        Storage::fake('local');
        $this->actingAs($user)
            ->post(route('finance.expenses.store'), [
                'branch_id' => $branch->id,
                'financial_category_id' => $category->id,
                'amount' => 40000,
                'incurred_at' => now()->format('Y-m-d H:i:s'),
                'proof' => UploadedFile::fake()->image('reset-proof.jpg'),
            ])
            ->assertSessionHasNoErrors();
        $expense = OperationalExpense::query()->firstOrFail();
        $path = (string) $expense->proof_path;
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession($user, $branch, 100000);
        $this->actingAs($user)
            ->post(route('finance.expenses.pay', $expense), [
                'payment_method_id' => $cash->id,
                'cash_session_id' => $session->id,
                'paid_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();
        $paymentId = (int) $expense->fresh()->payment_id;

        $preview = app(OperationalDataResetService::class)->preview($branch->company_id, $branch);
        $this->assertSame(1, $preview['operational_expenses']);
        app(OperationalDataResetService::class)->reset($branch->company_id, $branch, false);

        $this->assertDatabaseMissing('operational_expenses', ['id' => $expense->id]);
        $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
        $this->assertDatabaseMissing('cash_transactions', ['payment_id' => $paymentId]);
        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseHas('financial_categories', ['id' => $category->id]);
    }

    /** @return array{0: User, 1: Branch, 2: FinancialCategory} */
    private function fixture(string $roleSlug = 'branch-manager'): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $user = $this->userForRole($roleSlug, $branch);
        $category = FinancialCategory::query()
            ->where('company_id', $branch->company_id)
            ->where('code', 'OPERATING')
            ->firstOrFail();

        return [$user, $branch, $category];
    }

    private function userForRole(string $roleSlug, Branch $branch): User
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
            [
                'branch_id' => $roleSlug === 'branch-manager' ? $branch->id : null,
                'assigned_at' => now(),
            ],
        );

        return $user;
    }

    private function expense(
        User $user,
        Branch $branch,
        FinancialCategory $category,
        float $amount,
        string $vendor = 'Vendor Operasional',
    ): OperationalExpense {
        $this->actingAs($user)
            ->post(route('finance.expenses.store'), [
                'branch_id' => $branch->id,
                'financial_category_id' => $category->id,
                'amount' => $amount,
                'incurred_at' => now()->format('Y-m-d H:i:s'),
                'vendor_name' => $vendor,
                'notes' => 'Fixture expense operasional.',
            ])
            ->assertSessionHasNoErrors();

        return OperationalExpense::query()->latest('id')->firstOrFail();
    }
}
