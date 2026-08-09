<?php

namespace Tests\Feature\Finance;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\FinancialCategory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_complete_finance_master_center(): void
    {
        [$user, $branch] = $this->fixture('super-admin');

        $this->actingAs($user)
            ->get(route('finance.masters.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('finance/master-data/index')
                ->has('paymentMethods', 4)
                ->has('financialCategories', 7)
                ->has('cashRegisters', 1)
                ->where('cashRegisters.0.branch_id', $branch->id)
                ->where('summary.active_payment_methods', 4)
                ->where('summary.active_financial_categories', 7)
                ->where('summary.active_cash_registers', 1)
                ->where('summary.open_cash_sessions', 0)
                ->where('permissions.managePaymentMethods', true)
                ->where('permissions.manageCategories', true)
                ->where('permissions.manageCashRegisters', true)
                ->where('permissions.manageCashSessions', true));
    }

    public function test_payment_method_crud_preserves_used_code_and_type(): void
    {
        [$user, $branch] = $this->fixture('super-admin');

        $this->actingAs($user)
            ->post(route('finance.payment-methods.store'), [
                'code' => 'e-wallet',
                'name' => 'Dompet Digital',
                'type' => 'other',
                'requires_reference' => true,
                'sort_order' => 15,
            ])
            ->assertSessionHasNoErrors();

        $method = PaymentMethod::query()->where('code', 'E-WALLET')->firstOrFail();
        $this->assertTrue($method->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'finance.payment_method.created',
            'subject_id' => $method->id,
        ]);

        $this->actingAs($user)
            ->put(route('finance.payment-methods.update', $method), [
                'code' => 'e-wallet',
                'name' => 'Dompet Digital Utama',
                'type' => 'qris',
                'requires_reference' => true,
                'sort_order' => 10,
            ])
            ->assertSessionHasNoErrors();

        $method->refresh();
        $this->assertSame('qris', $method->type);
        $this->assertSame('Dompet Digital Utama', $method->name);

        Payment::query()->create([
            'branch_id' => $branch->id,
            'payment_method_id' => $method->id,
            'payment_number' => 'PNG-PAY-MASTER-001',
            'direction' => 'in',
            'type' => 'rental',
            'status' => 'completed',
            'amount' => 100000,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->put(route('finance.payment-methods.update', $method), [
                'code' => 'EWALLET-NEW',
                'name' => 'Dompet Digital Utama',
                'type' => 'card',
                'requires_reference' => true,
                'sort_order' => 10,
            ])
            ->assertRedirect(route('finance.masters.index'))
            ->assertSessionHasErrors('code');

        $method->refresh();
        $this->assertSame('E-WALLET', $method->code);
        $this->assertSame('qris', $method->type);
    }

    public function test_cash_payment_method_cannot_be_deactivated_during_open_session(): void
    {
        [$user, , $register] = $this->fixture('branch-manager');
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openSession($register, $user);

        $this->grantPermission($user, 'finance.payment_methods.manage');

        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->patch(route('finance.payment-methods.toggle-status', $cash))
            ->assertRedirect(route('finance.masters.index'))
            ->assertSessionHasErrors('payment_method');

        $session->update([
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
            'actual_closing_balance' => 0,
        ]);

        $this->actingAs($user)
            ->patch(route('finance.payment-methods.toggle-status', $cash))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payment_methods', [
            'id' => $cash->id,
            'is_active' => false,
        ]);
    }

    public function test_category_crud_protects_core_and_used_classification(): void
    {
        [$user, $branch] = $this->fixture('super-admin');

        $this->actingAs($user)
            ->post(route('finance.financial-categories.store'), [
                'code' => 'marketing',
                'name' => 'Biaya Marketing',
                'type' => 'expense',
            ])
            ->assertSessionHasNoErrors();

        $category = FinancialCategory::query()->where('code', 'MARKETING')->firstOrFail();
        Payment::query()->create([
            'branch_id' => $branch->id,
            'payment_method_id' => PaymentMethod::query()->where('code', 'TRANSFER')->value('id'),
            'financial_category_id' => $category->id,
            'payment_number' => 'PNG-PAY-MASTER-002',
            'direction' => 'out',
            'type' => 'expense',
            'status' => 'completed',
            'amount' => 250000,
            'paid_at' => now(),
            'received_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->put(route('finance.financial-categories.update', $category), [
                'code' => 'MARKETING-INCOME',
                'name' => 'Marketing Income',
                'type' => 'income',
            ])
            ->assertSessionHasErrors('code');

        $rental = FinancialCategory::query()->where('code', 'RENTAL')->firstOrFail();
        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->patch(route('finance.financial-categories.toggle-status', $rental))
            ->assertSessionHasErrors('financial_category');

        $this->assertTrue($rental->fresh()->is_active);
        $this->assertSame('MARKETING', $category->fresh()->code);
        $this->assertSame('expense', $category->fresh()->type);
    }

    public function test_cash_register_lifecycle_blocks_branch_move_code_change_and_open_session_deactivation(): void
    {
        [$user, $branch] = $this->fixture('super-admin');
        $otherBranch = $this->createBranch($branch, 'MDN', 'Together Kamera Madiun');

        $this->actingAs($user)
            ->post(route('finance.cash-registers.store'), [
                'branch_id' => $branch->id,
                'code' => 'front-desk',
                'name' => 'Kasir Front Desk',
            ])
            ->assertSessionHasNoErrors();

        $register = CashRegister::query()->where('code', 'FRONT-DESK')->firstOrFail();
        $session = $this->openSession($register, $user);

        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->put(route('finance.cash-registers.update', $register), [
                'branch_id' => $otherBranch->id,
                'code' => 'FRONT-DESK-NEW',
                'name' => 'Kasir Dipindah',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->patch(route('finance.cash-registers.toggle-status', $register))
            ->assertSessionHasErrors('cash_register');

        $session->update([
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => now(),
            'actual_closing_balance' => 0,
        ]);

        $this->actingAs($user)
            ->patch(route('finance.cash-registers.toggle-status', $register))
            ->assertSessionHasNoErrors();

        $register->refresh();
        $this->assertFalse($register->is_active);
        $this->assertSame($branch->id, $register->branch_id);
        $this->assertSame('FRONT-DESK', $register->code);
    }

    public function test_branch_manager_is_scoped_to_current_branch_and_cannot_manage_company_masters(): void
    {
        [$user, $branch] = $this->fixture('branch-manager');
        $otherBranch = $this->createBranch($branch, 'MDN', 'Together Kamera Madiun');
        $otherRegister = CashRegister::query()->create([
            'branch_id' => $otherBranch->id,
            'code' => 'MAIN',
            'name' => 'Kasir Utama Madiun',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('finance.masters.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cashRegisters', 1)
                ->where('cashRegisters.0.branch_id', $branch->id)
                ->has('branches', 1)
                ->where('permissions.managePaymentMethods', false)
                ->where('permissions.manageCategories', false)
                ->where('permissions.manageCashRegisters', true));

        $this->actingAs($user)
            ->post(route('finance.payment-methods.store'), [
                'code' => 'TEST',
                'name' => 'Test',
                'type' => 'other',
                'requires_reference' => false,
                'sort_order' => 99,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('finance.cash-registers.update', $otherRegister), [
                'branch_id' => $otherBranch->id,
                'code' => 'MAIN',
                'name' => 'Kasir Lintas Cabang',
            ])
            ->assertForbidden();

        $this->assertSame('Kasir Utama Madiun', $otherRegister->fresh()->name);
    }

    public function test_cashier_can_operate_sessions_without_master_mutation_permissions(): void
    {
        [$user, $branch, $register] = $this->fixture('cashier');

        $this->actingAs($user)
            ->get(route('finance.masters.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cashRegisters.0.branch_id', $branch->id)
                ->where('permissions.managePaymentMethods', false)
                ->where('permissions.manageCategories', false)
                ->where('permissions.manageCashRegisters', false)
                ->where('permissions.manageCashSessions', true));

        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->post(route('finance.cash-sessions.store', $register), [
                'opening_balance' => 100000,
                'opening_notes' => 'Shift pagi.',
            ])
            ->assertRedirect(route('finance.masters.index'))
            ->assertSessionHasNoErrors();

        $session = CashSession::query()->where('cash_register_id', $register->id)->firstOrFail();
        $this->actingAs($user)
            ->from(route('finance.masters.index'))
            ->post(route('finance.cash-sessions.close', $session), [
                'actual_closing_balance' => 100000,
                'closing_notes' => 'Shift selesai.',
            ])
            ->assertRedirect(route('finance.masters.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('closed', $session->fresh()->status);
    }

    /** @return array{0: User, 1: Branch, 2: CashRegister} */
    private function fixture(string $roleSlug): array
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
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'is_default' => true,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $user->id,
            'branch_id' => $role->scope === 'branch' ? $branch->id : null,
            'assigned_by' => null,
            'assigned_at' => $now,
            'expires_at' => null,
        ]);

        return [
            $user,
            $branch,
            CashRegister::query()->where('branch_id', $branch->id)->firstOrFail(),
        ];
    }

    private function openSession(CashRegister $register, User $user): CashSession
    {
        return CashSession::query()->create([
            'cash_register_id' => $register->id,
            'opened_by' => $user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => 0,
            'expected_closing_balance' => 0,
            'difference_amount' => 0,
        ]);
    }

    private function createBranch(Branch $source, string $code, string $name): Branch
    {
        return Branch::query()->create([
            'company_id' => $source->company_id,
            'code' => $code,
            'name' => $name,
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
    }

    private function grantPermission(User $user, string $slug): void
    {
        $roleId = (int) DB::table('role_user')->where('user_id', $user->id)->value('role_id');
        $permissionId = (int) DB::table('permissions')->where('slug', $slug)->value('id');

        DB::table('permission_role')->updateOrInsert(
            ['role_id' => $roleId, 'permission_id' => $permissionId],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}
