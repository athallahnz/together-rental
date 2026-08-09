<?php

namespace App\Domain\Finance;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;

class RefundEligibility
{
    /**
     * @return array{
     *     allowed: bool,
     *     field: string,
     *     reason: string|null,
     *     payment_amount: float,
     *     paid_refund_amount: float,
     *     reserved_refund_amount: float,
     *     refundable_amount: float
     * }
     */
    public function forPayment(Payment $payment, ?User $actor = null): array
    {
        $payment->loadMissing('branch');
        $paymentAmount = round((float) $payment->amount, 2);
        $paid = $this->sum($payment, ['paid']);
        $reserved = $this->sum($payment, ['requested', 'approved']);
        $remaining = max(0, round($paymentAmount - $paid - $reserved, 2));

        if ($actor !== null) {
            if ($actor->company_id !== $payment->branch->company_id) {
                return $this->blocked(
                    'payment',
                    'Payment tidak tersedia untuk perusahaan pengguna.',
                    $paymentAmount,
                    $paid,
                    $reserved,
                    $remaining,
                );
            }

            if (
                $actor->current_branch_id !== $payment->branch_id
                && ! $actor->hasCompanyScopedRole()
            ) {
                return $this->blocked(
                    'payment',
                    'Aktifkan cabang payment sebelum mengajukan refund.',
                    $paymentAmount,
                    $paid,
                    $reserved,
                    $remaining,
                );
            }
        }

        if ($payment->status !== 'completed') {
            return $this->blocked(
                'payment',
                'Hanya payment completed yang dapat direfund.',
                $paymentAmount,
                $paid,
                $reserved,
                $remaining,
            );
        }

        if ($payment->direction !== 'in') {
            return $this->blocked(
                'payment',
                'Hanya penerimaan dari pelanggan yang dapat direfund.',
                $paymentAmount,
                $paid,
                $reserved,
                $remaining,
            );
        }

        if ($paymentAmount <= 0 || $remaining <= 0) {
            return $this->blocked(
                'amount',
                'Tidak ada nominal payment yang masih dapat direfund.',
                $paymentAmount,
                $paid,
                $reserved,
                $remaining,
            );
        }

        return [
            'allowed' => true,
            'field' => 'payment',
            'reason' => null,
            'payment_amount' => $paymentAmount,
            'paid_refund_amount' => $paid,
            'reserved_refund_amount' => $reserved,
            'refundable_amount' => $remaining,
        ];
    }

    /**
     * @return array{approve: bool, reject: bool, process: bool, cancel: bool}
     */
    public function actions(Refund $refund, User $actor): array
    {
        $separatedApprover = (int) $refund->requested_by !== (int) $actor->id;

        return [
            'approve' => $actor->can('refunds.approve')
                && $refund->status === 'requested'
                && $separatedApprover,
            'reject' => $actor->can('refunds.approve')
                && $refund->status === 'requested'
                && $separatedApprover,
            'process' => $actor->can('refunds.process')
                && $refund->status === 'approved',
            'cancel' => $actor->can('refunds.cancel')
                && in_array($refund->status, ['requested', 'approved'], true),
        ];
    }

    /** @param list<string> $statuses */
    private function sum(Payment $payment, array $statuses): float
    {
        return round((float) Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', $statuses)
            ->sum('amount'), 2);
    }

    /**
     * @return array{
     *     allowed: false,
     *     field: string,
     *     reason: string,
     *     payment_amount: float,
     *     paid_refund_amount: float,
     *     reserved_refund_amount: float,
     *     refundable_amount: float
     * }
     */
    private function blocked(
        string $field,
        string $reason,
        float $paymentAmount,
        float $paid,
        float $reserved,
        float $remaining,
    ): array {
        return [
            'allowed' => false,
            'field' => $field,
            'reason' => $reason,
            'payment_amount' => $paymentAmount,
            'paid_refund_amount' => $paid,
            'reserved_refund_amount' => $reserved,
            'refundable_amount' => $remaining,
        ];
    }
}
