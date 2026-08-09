<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\PaymentManager;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RentalFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\InteractsWithFinance;
use Tests\TestCase;

class RefundWorkflowTest extends TestCase
{
    use InteractsWithFinance;
    use RefreshDatabase;

    public function test_partial_refund_request_reserves_refundable_amount_and_blocks_over_refund(): void
    {
        [$requester, , , $branch, $customer] = $this->fixture();
        $payment = $this->nonCashPayment($requester, $branch, $customer, 500000);
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($requester)
            ->post(route('finance.payments.refunds.store', $payment), [
                'amount' => 200000,
                'payment_method_id' => $method->id,
                'reason' => 'Pelanggan menerima pengembalian sebagian biaya rental.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $refund = Refund::query()->firstOrFail();
        $this->assertSame('requested', $refund->status);
        $this->assertSame('partial', $refund->refund_type);
        $this->assertSame($requester->id, $refund->requested_by);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Refund::class,
            'subject_id' => $refund->id,
            'event' => 'refund.requested',
        ]);

        $this->actingAs($requester)
            ->from(route('finance.payments.show', $payment))
            ->post(route('finance.payments.refunds.store', $payment), [
                'amount' => 350000,
                'payment_method_id' => $method->id,
                'reason' => 'Nominal ini sengaja melebihi sisa refundable payment.',
            ])
            ->assertRedirect(route('finance.payments.show', $payment))
            ->assertSessionHasErrors('amount');

        $this->actingAs($requester)
            ->get(route('finance.payments.show', $payment))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('refundEligibility.refundable_amount', 300000)
                ->where('refundEligibility.reserved_refund_amount', 200000)
                ->has('payment.refunds', 1));
    }

    public function test_requester_cannot_approve_own_refund_but_separate_approver_can(): void
    {
        [$requester, $approver, , $branch, $customer] = $this->fixture();
        $refund = $this->requestedRefund($requester, $branch, $customer);

        $this->actingAs($requester)
            ->from(route('finance.refunds.show', $refund))
            ->post(route('finance.refunds.approve', $refund))
            ->assertRedirect(route('finance.refunds.show', $refund))
            ->assertSessionHasErrors('refund');
        $this->assertSame('requested', $refund->fresh()->status);

        $this->actingAs($approver)
            ->post(route('finance.refunds.approve', $refund))
            ->assertSessionHasNoErrors();

        $refund->refresh();
        $this->assertSame('approved', $refund->status);
        $this->assertSame($approver->id, $refund->approved_by);
        $this->assertNotNull($refund->approved_at);
    }

    public function test_rejection_releases_reserved_amount_for_a_new_request(): void
    {
        [$requester, $approver, , $branch, $customer] = $this->fixture();
        $refund = $this->requestedRefund($requester, $branch, $customer, 300000);
        $payment = $refund->payment()->firstOrFail();
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();

        $this->actingAs($approver)
            ->post(route('finance.refunds.reject', $refund), [
                'reason' => 'Dokumen pendukung belum sesuai dengan hasil pemeriksaan.',
            ])
            ->assertSessionHasNoErrors();
        $this->assertSame('rejected', $refund->fresh()->status);

        $this->actingAs($requester)
            ->post(route('finance.payments.refunds.store', $payment), [
                'amount' => 500000,
                'payment_method_id' => $method->id,
                'reason' => 'Pengajuan ulang setelah dokumen dan alasan diperbaiki.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'status' => 'requested',
            'refund_type' => 'full',
            'amount' => 500000,
        ]);
    }

    public function test_non_cash_refund_requires_reference_and_proof_then_preserves_payment(): void
    {
        Storage::fake('local');
        [$requester, $approver, $processor, $branch, $customer] = $this->fixture();
        $refund = $this->requestedRefund($requester, $branch, $customer, 175000);
        $payment = $refund->payment()->firstOrFail();
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund));

        $this->actingAs($processor)
            ->from(route('finance.refunds.show', $refund))
            ->post(route('finance.refunds.process', $refund), [
                'proof' => UploadedFile::fake()->image('refund.jpg'),
            ])
            ->assertRedirect(route('finance.refunds.show', $refund))
            ->assertSessionHasErrors('external_reference');

        $this->actingAs($processor)
            ->post(route('finance.refunds.process', $refund), [
                'external_reference' => 'TRX-REFUND-0001',
                'proof' => UploadedFile::fake()->image('refund-final.jpg'),
                'notes' => 'Transfer refund telah diterima pelanggan.',
            ])
            ->assertSessionHasNoErrors();

        $refund->refresh();
        $this->assertSame('paid', $refund->status);
        $this->assertSame($processor->id, $refund->processed_by);
        $this->assertSame('TRX-REFUND-0001', $refund->external_reference);
        $this->assertNotNull($refund->proof_path);
        Storage::disk('local')->assertExists((string) $refund->proof_path);
        $this->actingAs($processor)
            ->get(route('finance.refunds.proof', $refund))
            ->assertOk();
        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertSame('500000.00', $payment->fresh()->amount);
    }

    public function test_cash_refund_uses_new_open_session_and_appends_outgoing_ledger(): void
    {
        Storage::fake('local');
        [$requester, $approver, $processor, $branch, $customer] = $this->fixture();
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();
        $oldSession = $this->openCashSession($requester, $branch, 100000);
        $payment = app(PaymentManager::class)->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $cash->id,
            'cash_session_id' => $oldSession->id,
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'rental_return',
            'amount' => 500000,
        ], $requester);
        $oldSession->update(['status' => 'closed', 'closed_at' => now()]);
        $newSession = $this->openCashSession($processor, $branch, 700000);

        $this->actingAs($requester)->post(
            route('finance.payments.refunds.store', $payment),
            [
                'amount' => 125000,
                'payment_method_id' => $cash->id,
                'reason' => 'Refund tunai dilakukan setelah sesi penerimaan ditutup.',
            ],
        );
        $refund = Refund::query()->latest('id')->firstOrFail();
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund));
        $this->actingAs($processor)->post(route('finance.refunds.process', $refund), [
            'cash_session_id' => $newSession->id,
            'proof' => UploadedFile::fake()->image('cash-refund.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('closed', $oldSession->fresh()->status);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_session_id' => $newSession->id,
            'payment_id' => null,
            'refund_id' => $refund->id,
            'direction' => 'out',
            'type' => 'customer_refund',
            'amount' => 125000,
            'balance_after' => 575000,
        ]);
        $this->assertSame('paid', $refund->fresh()->status);
    }

    public function test_approved_refund_can_be_cancelled_without_deleting_history_or_ledger(): void
    {
        [$requester, $approver, , $branch, $customer] = $this->fixture();
        $refund = $this->requestedRefund($requester, $branch, $customer, 225000);
        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund));

        $this->actingAs($approver)
            ->post(route('finance.refunds.cancel', $refund), [
                'reason' => 'Pelanggan membatalkan permintaan sebelum payout dilakukan.',
            ])
            ->assertSessionHasNoErrors();

        $refund->refresh();
        $this->assertSame('cancelled', $refund->status);
        $this->assertSame($approver->id, $refund->cancelled_by);
        $this->assertDatabaseCount('refunds', 1);
        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Refund::class,
            'subject_id' => $refund->id,
            'event' => 'refund.cancelled',
        ]);
    }

    public function test_active_or_paid_refund_blocks_payment_void(): void
    {
        [$requester, $approver, $processor, $branch, $customer] = $this->fixture();
        Storage::fake('local');
        $refund = $this->requestedRefund($requester, $branch, $customer, 100000);
        $payment = $refund->payment()->firstOrFail();

        $this->actingAs($requester)
            ->post(route('finance.payments.void', $payment), [
                'reason' => 'Mencoba void saat refund masih aktif.',
            ])
            ->assertSessionHasErrors('payment');

        $this->actingAs($approver)->post(route('finance.refunds.approve', $refund));
        $this->actingAs($processor)->post(route('finance.refunds.process', $refund), [
            'external_reference' => 'TRX-REFUND-BLOCK-VOID',
            'proof' => UploadedFile::fake()->image('paid-refund.jpg'),
        ]);

        $this->actingAs($requester)
            ->post(route('finance.payments.void', $payment), [
                'reason' => 'Mencoba void setelah refund dibayarkan.',
            ])
            ->assertSessionHasErrors('payment');
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_refund_center_is_branch_isolated_and_requires_permission(): void
    {
        [$requester, , , $branch, $customer] = $this->fixture();
        $refund = $this->requestedRefund($requester, $branch, $customer);
        $foreignBranch = Branch::query()->create([
            'company_id' => $branch->company_id,
            'code' => 'MDN',
            'name' => 'Together Kamera Madiun',
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
        ]);
        $foreignPayment = Payment::query()->create([
            'branch_id' => $foreignBranch->id,
            'payment_method_id' => $refund->payment_method_id,
            'payment_number' => 'PAY-MDN-REFUND-001',
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'rental_return',
            'status' => 'completed',
            'amount' => 100000,
            'paid_at' => now(),
        ]);
        $foreignRefund = Refund::query()->create([
            'branch_id' => $foreignBranch->id,
            'payment_id' => $foreignPayment->id,
            'payment_method_id' => $refund->payment_method_id,
            'refund_number' => 'RFD-MDN-REFUND-001',
            'refund_type' => 'full',
            'amount' => 100000,
            'status' => 'requested',
            'reason' => 'Refund cabang lain untuk pengujian isolasi akses.',
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($requester)
            ->get(route('finance.refunds.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('finance/refunds/index')
                ->has('refunds.data', 1)
                ->where('refunds.data.0.id', $refund->id));
        $this->actingAs($requester)
            ->get(route('finance.refunds.show', $foreignRefund))
            ->assertNotFound();

        $inventory = $this->userWithRole($branch, 'inventory-staff');
        $this->actingAs($inventory)
            ->get(route('finance.refunds.index'))
            ->assertForbidden();
    }

    /** @return array{User, User, User, Branch, Customer} */
    private function fixture(): array
    {
        $this->seed(RentalFoundationSeeder::class);
        $branch = Branch::query()->where('code', 'PNG')->firstOrFail();
        $requester = $this->userWithRole($branch, 'branch-manager');
        $approver = $this->userWithRole($branch, 'branch-manager');
        $processor = $this->userWithRole($branch, 'cashier');
        $customer = Customer::query()->create([
            'company_id' => $branch->company_id,
            'registered_branch_id' => $branch->id,
            'customer_number' => 'PNG-CUS-REFUND-001',
            'name' => 'Pelanggan Refund Workflow',
            'phone' => '081234567890',
            'status' => 'active',
            'risk_level' => 'normal',
        ]);

        return [$requester, $approver, $processor, $branch, $customer];
    }

    private function userWithRole(Branch $branch, string $roleSlug): User
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
            ['branch_id' => $branch->id, 'assigned_at' => now()],
        );

        return $user;
    }

    private function nonCashPayment(
        User $actor,
        Branch $branch,
        Customer $customer,
        float $amount,
    ): Payment {
        $method = PaymentMethod::query()->where('code', 'QRIS')->firstOrFail();

        return app(PaymentManager::class)->record($branch, [
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'direction' => 'in',
            'type' => 'rental',
            'source_context' => 'rental_return',
            'amount' => $amount,
            'external_reference' => 'QRIS-REFUND-SOURCE-001',
        ], $actor);
    }

    private function requestedRefund(
        User $requester,
        Branch $branch,
        Customer $customer,
        float $amount = 200000,
    ): Refund {
        $payment = $this->nonCashPayment($requester, $branch, $customer, 500000);
        $method = PaymentMethod::query()->where('code', 'TRANSFER')->firstOrFail();
        $this->actingAs($requester)->post(
            route('finance.payments.refunds.store', $payment),
            [
                'amount' => $amount,
                'payment_method_id' => $method->id,
                'reason' => 'Pengajuan refund valid untuk pengujian workflow.',
            ],
        )->assertSessionHasNoErrors();

        return Refund::query()->latest('id')->firstOrFail();
    }
}
