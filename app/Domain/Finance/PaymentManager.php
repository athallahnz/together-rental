<?php

namespace App\Domain\Finance;

use App\Domain\Rentals\RentalNumberGenerator;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\BranchTransferExpense;
use App\Models\CashSession;
use App\Models\FinancialCategory;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Rental;
use App\Models\RentalExtension;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly CashLedger $cashLedger,
        private readonly PaymentVoidEligibility $voidEligibility,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Branch $branch, array $data, User $actor): Payment
    {
        return DB::transaction(function () use ($branch, $data, $actor): Payment {
            $lockedBranch = Branch::query()->lockForUpdate()->findOrFail($branch->id);
            $this->guardBranch($lockedBranch, $actor);

            $amount = (float) ($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal payment harus lebih dari nol.',
                ]);
            }

            $method = PaymentMethod::query()
                ->where('company_id', $lockedBranch->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->find((int) ($data['payment_method_id'] ?? 0));
            if ($method === null) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Metode pembayaran tidak tersedia.',
                ]);
            }

            $externalReference = $this->nullableString($data['external_reference'] ?? null);
            if ($method->requires_reference && $externalReference === null) {
                throw ValidationException::withMessages([
                    'payment_reference' => 'Referensi pembayaran wajib diisi.',
                ]);
            }

            $cashSession = $this->cashSession(
                $method,
                $lockedBranch,
                $data['cash_session_id'] ?? null,
            );
            $categoryId = $this->categoryId($lockedBranch, $data);
            $direction = (string) ($data['direction'] ?? 'in');
            if (! in_array($direction, ['in', 'out'], true)) {
                throw ValidationException::withMessages([
                    'direction' => 'Arah payment tidak valid.',
                ]);
            }
            $type = $this->requiredString($data['type'] ?? null, 'type');
            $sourceContext = $this->requiredString(
                $data['source_context'] ?? null,
                'source_context',
            );
            if (! in_array($sourceContext, [
                'booking',
                'rental_checkout',
                'rental_return',
                'rental_extension',
                'transfer_expense',
            ], true)) {
                throw ValidationException::withMessages([
                    'source_context' => 'Konteks sumber payment tidak valid.',
                ]);
            }

            $payment = Payment::query()->create([
                'branch_id' => $lockedBranch->id,
                'customer_id' => $data['customer_id'] ?? null,
                'booking_id' => $data['booking_id'] ?? null,
                'rental_id' => $data['rental_id'] ?? null,
                'rental_extension_id' => $data['rental_extension_id'] ?? null,
                'payment_method_id' => $method->id,
                'financial_category_id' => $categoryId,
                'cash_session_id' => $cashSession?->id,
                'payment_number' => $this->numbers->nextPayment($lockedBranch),
                'direction' => $direction,
                'type' => $type,
                'source_context' => $sourceContext,
                'status' => 'completed',
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now(),
                'external_reference' => $externalReference,
                'proof_path' => $data['proof_path'] ?? null,
                'notes' => $this->nullableString($data['notes'] ?? null),
                'received_by' => $actor->id,
            ]);

            if ($cashSession !== null) {
                $this->cashLedger->recordPayment($payment, $actor);
            }

            return $payment;
        }, 3);
    }

    public function void(Payment $payment, string $reason, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $reason, $actor): Payment {
            $locked = Payment::query()
                ->with(['branch', 'paymentMethod'])
                ->lockForUpdate()
                ->findOrFail($payment->id);
            $this->guardBranch($locked->branch, $actor);

            if ($locked->status === 'void') {
                return $locked;
            }

            $eligibility = $this->voidEligibility->evaluate($locked, $actor);
            if (! $eligibility['allowed']) {
                throw ValidationException::withMessages([
                    $eligibility['field'] => (string) $eligibility['reason'],
                ]);
            }

            if ($locked->paymentMethod->type === 'cash') {
                $this->cashLedger->reversePayment($locked, $actor, $reason);
            }

            $this->reverseOperationalTotals($locked, $actor, $reason);
            $locked->forceFill([
                'status' => 'void',
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            $locked->load('paymentMethod');

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function categoryId(Branch $branch, array $data): ?int
    {
        $query = FinancialCategory::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true);

        if (! empty($data['financial_category_id'])) {
            $category = $query->find((int) $data['financial_category_id']);
        } elseif (! empty($data['financial_category_code'])) {
            $category = $query->where('code', $data['financial_category_code'])->first();
        } else {
            return null;
        }

        if ($category === null) {
            throw ValidationException::withMessages([
                'financial_category_id' => 'Kategori keuangan tidak tersedia.',
            ]);
        }

        return (int) $category->id;
    }

    private function cashSession(
        PaymentMethod $method,
        Branch $branch,
        mixed $cashSessionId,
    ): ?CashSession {
        if ($method->type !== 'cash') {
            return null;
        }
        if ($cashSessionId === null || $cashSessionId === '') {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Sesi kas aktif wajib dipilih untuk pembayaran tunai.',
            ]);
        }

        $session = CashSession::query()
            ->with('register')
            ->where('status', 'open')
            ->lockForUpdate()
            ->find((int) $cashSessionId);
        if (
            $session === null
            || ! $session->register->is_active
            || $session->register->branch_id !== $branch->id
        ) {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Sesi kas tidak aktif atau bukan milik cabang transaksi.',
            ]);
        }

        return $session;
    }

    private function reverseOperationalTotals(
        Payment $payment,
        User $actor,
        string $reason,
    ): void {
        if ($payment->source_context === 'transfer_expense') {
            $expense = BranchTransferExpense::query()
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->first();

            if ($expense === null || $expense->status !== 'paid') {
                throw ValidationException::withMessages([
                    'payment' => 'Biaya transfer sumber tidak lagi berstatus dibayar.',
                ]);
            }

            $expense->forceFill([
                'status' => 'void',
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            return;
        }

        if ($payment->source_context === 'rental_extension') {
            $extension = $payment->rental_extension_id === null
                ? null
                : RentalExtension::query()->lockForUpdate()->find($payment->rental_extension_id);

            if ($extension === null) {
                throw ValidationException::withMessages([
                    'payment' => 'Perpanjangan sumber payment tidak ditemukan.',
                ]);
            }

            $extension->forceFill([
                'paid_amount' => max(0, (float) $extension->paid_amount - (float) $payment->amount),
            ])->save();
        }

        $booking = $payment->booking_id === null
            ? null
            : Booking::query()->lockForUpdate()->find($payment->booking_id);
        $rental = $payment->rental_id === null
            ? null
            : Rental::query()->lockForUpdate()->find($payment->rental_id);
        $amount = (float) $payment->amount;

        if ($payment->source_context === 'booking' && $booking !== null && $payment->type === 'deposit') {
            $booking->forceFill([
                'deposit_paid' => max(0, (float) $booking->deposit_paid - $amount),
            ])->save();
        }

        if ($rental === null) {
            return;
        }

        if ($payment->type === 'rental') {
            $updates = [
                'paid_amount' => max(0, (float) $rental->paid_amount - $amount),
                'balance_due' => (float) $rental->balance_due + $amount,
            ];
            if ($payment->source_context === 'booking') {
                $updates['booking_payment_amount'] = max(
                    0,
                    (float) $rental->booking_payment_amount - $amount,
                );
            }
            $rental->forceFill($updates)->save();

            return;
        }

        if ($payment->type === 'deposit') {
            $rental->forceFill([
                'deposit_amount' => max(0, (float) $rental->deposit_amount - $amount),
            ])->save();
        }
    }

    private function guardBranch(Branch $branch, User $actor): void
    {
        if ($actor->company_id !== $branch->company_id) {
            abort(404);
        }
        if ($actor->current_branch_id !== $branch->id && ! $actor->hasCompanyScopedRole()) {
            abort(403, 'Aktifkan cabang transaksi sebelum mencatat payment.');
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function requiredString(mixed $value, string $key): string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            throw ValidationException::withMessages([
                $key => 'Nilai wajib diisi.',
            ]);
        }

        return $value;
    }
}
