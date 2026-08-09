<?php

namespace App\Domain\Finance;

use App\Models\CashSession;
use App\Models\CashTransaction;
use App\Models\FinancialCategory;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashLedger
{
    public function recordPayment(Payment $payment, User $actor, ?string $description = null): CashTransaction
    {
        if ($payment->cash_session_id === null) {
            throw ValidationException::withMessages(['cash_session_id' => 'Sesi kas wajib dipilih untuk transaksi tunai.']);
        }

        return DB::transaction(function () use ($payment, $actor, $description): CashTransaction {
            $existing = CashTransaction::query()
                ->where('cash_session_id', $payment->cash_session_id)
                ->where('payment_id', $payment->id)
                ->where('type', $payment->type)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return $this->append($payment, $payment->direction, $payment->type, $actor, $description, $payment->paid_at);
        }, 3);
    }

    public function reversePayment(Payment $payment, User $actor, string $reason): CashTransaction
    {
        if ($payment->cash_session_id === null) {
            throw ValidationException::withMessages(['cash_session_id' => 'Payment tidak terhubung ke sesi kas.']);
        }

        return DB::transaction(function () use ($payment, $actor, $reason): CashTransaction {
            $type = $payment->type.'_void';
            $existing = CashTransaction::query()
                ->where('cash_session_id', $payment->cash_session_id)
                ->where('payment_id', $payment->id)
                ->where('type', $type)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $direction = $payment->direction === 'in' ? 'out' : 'in';

            return $this->append(
                $payment,
                $direction,
                $type,
                $actor,
                "Pembalikan Payment {$payment->payment_number}: {$reason}",
                now(),
            );
        }, 3);
    }

    public function recordRefund(
        Refund $refund,
        CashSession $cashSession,
        User $actor,
    ): CashTransaction {
        return DB::transaction(function () use ($refund, $cashSession, $actor): CashTransaction {
            $existing = CashTransaction::query()
                ->where('refund_id', $refund->id)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $session = CashSession::query()
                ->with('register.branch')
                ->lockForUpdate()
                ->findOrFail($cashSession->id);
            if ($session->status !== 'open') {
                throw ValidationException::withMessages([
                    'cash_session_id' => 'Sesi kas refund sudah tidak aktif.',
                ]);
            }
            if (
                $session->register->branch_id !== $refund->branch_id
                || $actor->company_id !== $session->register->branch->company_id
            ) {
                throw ValidationException::withMessages([
                    'cash_session_id' => 'Sesi kas harus berasal dari cabang refund.',
                ]);
            }

            $categoryId = FinancialCategory::query()
                ->where('company_id', $session->register->branch->company_id)
                ->where('code', 'REFUND')
                ->where('is_active', true)
                ->value('id');
            if ($categoryId === null) {
                throw ValidationException::withMessages([
                    'refund' => 'Kategori keuangan REFUND belum tersedia.',
                ]);
            }

            $incoming = (float) CashTransaction::query()
                ->where('cash_session_id', $session->id)
                ->where('direction', 'in')
                ->sum('amount');
            $outgoing = (float) CashTransaction::query()
                ->where('cash_session_id', $session->id)
                ->where('direction', 'out')
                ->sum('amount');
            $balance = (float) $session->opening_balance
                + $incoming
                - $outgoing
                - (float) $refund->amount;
            $sequence = CashTransaction::query()
                ->where('cash_session_id', $session->id)
                ->lockForUpdate()
                ->count() + 1;
            $paymentNumber = (string) Payment::query()
                ->whereKey($refund->payment_id)
                ->value('payment_number');

            return CashTransaction::query()->create([
                'cash_session_id' => $session->id,
                'payment_id' => null,
                'refund_id' => $refund->id,
                'financial_category_id' => $categoryId,
                'transaction_number' => 'CTX-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'direction' => 'out',
                'type' => 'customer_refund',
                'amount' => $refund->amount,
                'balance_after' => $balance,
                'occurred_at' => now(),
                'description' => "Refund {$refund->refund_number} untuk Payment {$paymentNumber}",
                'created_by' => $actor->id,
            ]);
        }, 3);
    }

    private function append(
        Payment $payment,
        string $direction,
        string $type,
        User $actor,
        ?string $description,
        mixed $occurredAt,
    ): CashTransaction {
        $session = CashSession::query()
            ->whereKey($payment->cash_session_id)
            ->lockForUpdate()
            ->first();

        if ($session === null || $session->status !== 'open') {
            throw ValidationException::withMessages(['cash_session_id' => 'Sesi kas tidak aktif.']);
        }

        $existing = CashTransaction::query()
            ->where('cash_session_id', $session->id)
            ->where('payment_id', $payment->id)
            ->where('type', $type)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $register = $session->register()->lockForUpdate()->firstOrFail();
        if ($register->branch_id !== $payment->branch_id) {
            throw ValidationException::withMessages(['cash_session_id' => 'Sesi kas harus berasal dari cabang payment.']);
        }

        $incoming = (float) CashTransaction::query()
            ->where('cash_session_id', $session->id)->where('direction', 'in')->sum('amount');
        $outgoing = (float) CashTransaction::query()
            ->where('cash_session_id', $session->id)->where('direction', 'out')->sum('amount');
        $amount = (float) $payment->amount;
        $balance = (float) $session->opening_balance + $incoming - $outgoing
            + ($direction === 'in' ? $amount : -$amount);
        $sequence = CashTransaction::query()
            ->where('cash_session_id', $session->id)->lockForUpdate()->count() + 1;

        return CashTransaction::query()->create([
            'cash_session_id' => $session->id,
            'payment_id' => $payment->id,
            'financial_category_id' => $payment->financial_category_id,
            'transaction_number' => 'CTX-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'direction' => $direction,
            'type' => $type,
            'amount' => $payment->amount,
            'balance_after' => $balance,
            'occurred_at' => $occurredAt,
            'description' => $description ?? $payment->notes,
            'created_by' => $actor->id,
        ]);
    }
}
