<?php

namespace App\Domain\Finance;

use App\Models\BranchTransferExpense;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\User;

class PaymentVoidEligibility
{
    /**
     * @return array{allowed: bool, field: string, reason: string|null}
     */
    public function evaluate(Payment $payment, ?User $actor = null): array
    {
        if ($actor !== null) {
            if ($actor->company_id !== $payment->branch->company_id) {
                return $this->blocked('payment', 'Payment tidak tersedia untuk perusahaan pengguna.');
            }

            if (
                $actor->current_branch_id !== $payment->branch_id
                && ! $actor->hasCompanyScopedRole()
            ) {
                return $this->blocked(
                    'payment',
                    'Aktifkan cabang payment sebelum melakukan void.',
                );
            }
        }

        if ($payment->status === 'void') {
            return $this->blocked('payment', 'Payment ini sudah di-void.');
        }

        if ($payment->status !== 'completed') {
            return $this->blocked('payment', 'Hanya payment completed yang dapat di-void.');
        }

        if (Refund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', ['requested', 'approved', 'paid'])
            ->exists()) {
            return $this->blocked(
                'payment',
                'Payment memiliki refund aktif atau sudah dibayar dan tidak dapat di-void.',
            );
        }

        if ($payment->source_context === null) {
            return $this->blocked(
                'payment',
                'Payment lama tanpa konteks sumber harus dikoreksi melalui refund atau koreksi keuangan.',
            );
        }

        if (! in_array($payment->source_context, [
            'booking',
            'rental_checkout',
            'rental_return',
            'transfer_expense',
        ], true)) {
            return $this->blocked(
                'payment',
                'Konteks sumber payment tidak mendukung void otomatis.',
            );
        }

        if ($payment->source_context === 'rental_return') {
            return $this->blocked(
                'payment',
                'Payment saat pengembalian tidak dapat di-void karena return sudah final. Gunakan refund atau koreksi keuangan.',
            );
        }

        if ($payment->rental_id !== null) {
            $rentalStatus = Rental::query()
                ->whereKey($payment->rental_id)
                ->value('status');

            if (in_array($rentalStatus, ['returned', 'completed'], true)) {
                return $this->blocked(
                    'payment',
                    'Payment rental yang sudah ditutup tidak dapat di-void. Gunakan refund atau koreksi keuangan.',
                );
            }
        }

        if (
            $payment->source_context === 'transfer_expense'
            && ! BranchTransferExpense::query()
                ->where('payment_id', $payment->id)
                ->where('status', 'paid')
                ->exists()
        ) {
            return $this->blocked(
                'payment',
                'Biaya transfer sumber tidak lagi berstatus dibayar dan tidak dapat di-void dari Payment Center.',
            );
        }

        $method = PaymentMethod::query()->find($payment->payment_method_id);
        if ($method === null) {
            return $this->blocked('payment', 'Metode payment tidak lagi tersedia.');
        }

        if ($method->type === 'cash') {
            $session = $payment->cash_session_id === null
                ? null
                : CashSession::query()->find($payment->cash_session_id);

            if ($session === null || $session->status !== 'open') {
                return $this->blocked(
                    'cash_session_id',
                    'Sesi kas payment sudah ditutup. Gunakan workflow refund pada sesi kas aktif.',
                );
            }
        }

        return [
            'allowed' => true,
            'field' => 'payment',
            'reason' => null,
        ];
    }

    /** @return array{allowed: false, field: string, reason: string} */
    private function blocked(string $field, string $reason): array
    {
        return [
            'allowed' => false,
            'field' => $field,
            'reason' => $reason,
        ];
    }
}
