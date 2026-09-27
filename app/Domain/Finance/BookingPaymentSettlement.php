<?php

namespace App\Domain\Finance;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;

/**
 * Completed incoming booking payments less paid refunds on the SAME source payments.
 * Requested/approved refunds reserve refundable amounts but are not paid out yet.
 * Gross payment rows are never rewritten; this service is a live financial projection.
 */
class BookingPaymentSettlement
{
    /** @return array{rental_paid: float, deposit_paid: float, rental_refunded: float, deposit_refunded: float} */
    public function summary(Booking $booking): array
    {
        $result = [
            'rental_paid' => 0.0,
            'deposit_paid' => 0.0,
            'rental_refunded' => 0.0,
            'deposit_refunded' => 0.0,
        ];

        $payments = Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'completed')
            ->where('direction', 'in')
            ->whereIn('type', ['rental', 'deposit'])
            ->get(['id', 'type', 'amount']);

        if ($payments->isEmpty()) {
            return $result;
        }

        $refunds = Refund::query()
            ->whereIn('payment_id', $payments->modelKeys())
            ->where('status', 'paid')
            ->selectRaw('payment_id, SUM(amount) as refunded_amount')
            ->groupBy('payment_id')
            ->pluck('refunded_amount', 'payment_id');

        foreach ($payments as $payment) {
            $refunded = round((float) ($refunds[$payment->id] ?? 0), 2);
            $gross = round((float) $payment->amount, 2);
            $type = $payment->type === 'deposit' ? 'deposit' : 'rental';
            $result[$type.'_paid'] += max(0, round($gross - $refunded, 2));
            $result[$type.'_refunded'] += $refunded;
        }

        foreach ($result as $key => $value) {
            $result[$key] = round($value, 2);
        }

        return $result;
    }

    public function net(Booking $booking, string $type): float
    {
        if (! in_array($type, ['rental', 'deposit'], true)) {
            throw new \InvalidArgumentException('Jenis pembayaran booking tidak dikenal.');
        }

        return $this->summary($booking)[$type.'_paid'];
    }
}
