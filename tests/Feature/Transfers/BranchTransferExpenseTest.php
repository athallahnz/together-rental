<?php

namespace Tests\Feature\Transfers;

use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\BranchTransferExpense;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class BranchTransferExpenseTest extends TestCase
{
    use InteractsWithFinance;
    use InteractsWithTransferFixtures;
    use RefreshDatabase;

    public function test_transfer_expense_creates_one_outgoing_payment_and_void_is_append_only(): void
    {
        $fixture = $this->transferFixture();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture));
        $transfer = BranchTransfer::query()->firstOrFail();
        $categoryId = (int) DB::table('financial_categories')
            ->where('company_id', $fixture['origin']->company_id)
            ->where('code', 'TRANSFER-SHIPPING')
            ->value('id');

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.expenses.store', $transfer), [
                'expense_branch_id' => $fixture['origin']->id,
                'financial_category_id' => $categoryId,
                'expense_type' => 'shipping',
                'estimated_amount' => 75000,
                'actual_amount' => 80000,
                'vendor_name' => 'Kurir Internal',
                'external_reference' => 'INV-KURIR-001',
                'notes' => 'Pengiriman aset ke Madiun.',
            ])
            ->assertSessionHasNoErrors();

        $expense = BranchTransferExpense::query()->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $session = $this->openCashSession(
            $fixture['originManager'],
            $fixture['origin'],
        );
        $payPayload = [
            'actual_amount' => 80000,
            'payment_method_id' => $method->id,
            'cash_session_id' => $session->id,
            'paid_at' => now()->format('Y-m-d H:i:s'),
            'external_reference' => 'INV-KURIR-001',
            'notes' => 'Dibayar tunai melalui sesi kas aktif.',
        ];

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.expenses.pay', [$transfer, $expense]), $payPayload)
            ->assertSessionHasNoErrors();
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.expenses.pay', [$transfer, $expense]), $payPayload)
            ->assertSessionHasNoErrors();

        $expense->refresh();
        $this->assertSame('paid', $expense->status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 1);
        $this->assertDatabaseHas('payments', [
            'id' => $expense->payment_id,
            'branch_id' => $fixture['origin']->id,
            'direction' => 'out',
            'type' => 'transfer_expense',
            'source_context' => 'transfer_expense',
            'status' => 'completed',
            'amount' => 80000,
        ]);

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.expenses.void', [$transfer, $expense]), [
                'reason' => 'Invoice vendor dibatalkan dan akan dicatat ulang.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('void', $expense->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'id' => $expense->payment_id,
            'status' => 'void',
        ]);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('cash_transactions', 2);
    }

    public function test_expense_cannot_be_charged_to_uninvolved_branch(): void
    {
        $fixture = $this->transferFixture();
        $other = Branch::query()->create([
            'company_id' => $fixture['origin']->company_id,
            'code' => 'SBY',
            'name' => 'Together Kamera Surabaya',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.store'), $this->serializedPayload($fixture));
        $transfer = BranchTransfer::query()->firstOrFail();

        $this->actingAs($fixture['originManager'])
            ->post(route('transfers.expenses.store', $transfer), [
                'expense_branch_id' => $other->id,
                'financial_category_id' => null,
                'expense_type' => 'shipping',
                'estimated_amount' => 10000,
                'actual_amount' => 0,
            ])
            ->assertSessionHasErrors('expense_branch_id');

        $this->assertDatabaseCount('branch_transfer_expenses', 0);
    }
}
