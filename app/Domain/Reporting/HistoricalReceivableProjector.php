<?php

namespace App\Domain\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Read-only, event-dated receivables for dates strictly before today.
 * A rental with insufficient event history is identified, never assigned a fabricated historical balance.
 * This is a reporting projection: booking/payment/return records and their current ledgers are not modified.
 */
class HistoricalReceivableProjector
{
    /**
     * @param  list<int>  $branchIds
     * @param  array{to: CarbonImmutable, status: string, search: string}  $filters
     * @return array{rows: list<array<string, mixed>>, meta: array<string, mixed>, branch_totals: array<int, float>, branch_unverified: array<int, int>}
     */
    public function project(array $branchIds, array $filters): array
    {
        $cutoff = $filters['to'];
        $end = $cutoff->format('Y-m-d H:i:s');
        $empty = [
            'rows' => [],
            'meta' => ['as_of' => $cutoff->toDateString(), 'unverified_count' => 0, 'verified_count' => 0,
                'verified_receivables' => 0.0, 'is_partial' => false],
            'branch_totals' => [],
            'branch_unverified' => [],
        ];

        if ($branchIds === []) {
            return $empty;
        }

        /** @var Collection<int, stdClass> $rentals */
        $rentals = DB::table('rentals as r')
            ->join('branches as b', 'b.id', '=', 'r.branch_id')
            ->join('customers as c', 'c.id', '=', 'r.customer_id')
            ->whereIn('r.branch_id', $branchIds)
            ->where(static function ($query) use ($end): void {
                $query->where('r.checked_out_at', '<=', $end)
                    ->orWhere('r.created_at', '<=', $end);
            })
            ->where(static function ($query) use ($end): void {
                $query->whereNull('r.deleted_at')->orWhere('r.deleted_at', '>', $end);
            })
            ->when($filters['search'] !== '', static function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(static function ($nested) use ($search): void {
                    $nested->where('r.rental_number', 'like', "%{$search}%")
                        ->orWhere('c.name', 'like', "%{$search}%")
                        ->orWhere('c.phone', 'like', "%{$search}%");
                });
            })
            ->select(['r.id', 'r.branch_id', 'r.booking_id', 'r.rental_number', 'r.legacy_number',
                'r.status', 'r.checked_out_at', 'r.returned_at', 'r.created_at', 'r.total_amount',
                'r.paid_amount', 'r.due_at', 'r.deposit_amount', 'c.name as customer_name',
                'b.code as branch_code'])
            ->orderBy('r.due_at')->get();

        if ($rentals->isEmpty()) {
            return $empty;
        }

        $rentalIds = $rentals->pluck('id')->all();
        $bookingIds = $rentals->pluck('booking_id')->filter()->unique()->values()->all();
        $payments = DB::table('payments')
            ->whereIn('branch_id', $branchIds)
            ->where(static function ($query) use ($rentalIds, $bookingIds): void {
                $query->whereIn('rental_id', $rentalIds);
                if ($bookingIds !== []) {
                    $query->orWhereIn('booking_id', $bookingIds);
                }
            })
            ->get(['id', 'booking_id', 'rental_id', 'direction', 'type', 'status', 'amount',
                'paid_at', 'voided_at', 'created_at']);
        $refunds = $payments->isEmpty() ? collect() : DB::table('refunds')
            ->whereIn('payment_id', $payments->pluck('id')->all())
            ->get(['id', 'payment_id', 'status', 'amount', 'processed_at', 'cancelled_at', 'created_at']);
        $extensions = DB::table('rental_extensions')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'status', 'approved_at', 'total_amount', 'previous_due_at']);
        $returns = DB::table('rental_returns')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'returned_at', 'created_at', 'total_charge_amount']);
        $adjustments = DB::table('rental_financial_adjustments')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'created_at', 'component', 'direction', 'amount']);
        $corrections = DB::table('rental_operational_corrections')->whereIn('rental_id', $rentalIds)
            ->get(['rental_id', 'opened_at', 'finalized_at']);
        $history = DB::table('rental_status_histories')->whereIn('rental_id', $rentalIds)
            ->orderBy('changed_at')->orderBy('id')
            ->get(['rental_id', 'to_status', 'changed_at', 'created_at']);

        $byRentalPayment = $payments->groupBy('rental_id');
        $byBookingPayment = $payments->filter(static fn (stdClass $p): bool => $p->booking_id !== null)
            ->groupBy('booking_id');
        $byPaymentRefund = $refunds->groupBy('payment_id');
        $byRentalExtension = $extensions->groupBy('rental_id');
        $byRentalReturn = $returns->groupBy('rental_id');
        $byRentalAdjustment = $adjustments->groupBy('rental_id');
        $byRentalCorrection = $corrections->groupBy('rental_id');
        $byRentalHistory = $history->groupBy('rental_id');

        $rows = [];
        $verifiedCount = 0;
        $unverifiedCount = 0;
        $verifiedTotal = 0.0;
        $branchTotals = [];
        $branchUnverified = [];

        foreach ($rentals as $rental) {
            $rentalId = (int) $rental->id;
            $branchId = (int) $rental->branch_id;
            $bookingId = $rental->booking_id === null ? null : (int) $rental->booking_id;
            $reasons = [];

            if ($rental->checked_out_at === null || (string) $rental->checked_out_at > $end) {
                // A known future checkout is excluded; a missing checkout for a non-draft rental is not silently dropped.
                if ($rental->checked_out_at !== null && (string) $rental->checked_out_at > $end
                    && (string) $rental->created_at <= $end) {
                    continue;
                }
                if ($rental->checked_out_at === null && $rental->status === 'draft') {
                    continue;
                }
                $reasons[] = 'CHECKOUT_NOT_PROVEN';
            }
            if ((string) $rental->created_at > $end) {
                $reasons[] = 'IMPORTED_AFTER_CUTOFF';
            }
            if ($rental->legacy_number !== null) {
                $reasons[] = 'LEGACY_EVENT_HISTORY';
            }
            if (in_array((string) $rental->status, ['cancelled', 'void', 'rejected'], true)) {
                $reasons[] = 'LATER_INVALIDATION_NEEDS_SNAPSHOT';
            }

            $dueAt = (string) $rental->due_at;
            foreach ($byRentalExtension->get($rentalId, collect()) as $extension) {
                if ($extension->approved_at !== null && (string) $extension->approved_at > $end
                    && ! in_array((string) $extension->status, ['cancelled', 'rejected', 'void'], true)) {
                    $reasons[] = 'LATER_EXTENSION_REQUIRES_PRICE_SNAPSHOT';
                    if ($extension->previous_due_at !== null) {
                        $dueAt = min($dueAt, (string) $extension->previous_due_at);
                    }
                }
            }
            foreach ($byRentalReturn->get($rentalId, collect()) as $return) {
                if ((string) $return->created_at > $end && (string) $return->returned_at <= $end) {
                    $reasons[] = 'BACKDATED_RETURN';
                }
                if ((string) $return->returned_at > $end && abs((float) $return->total_charge_amount) > 0.009) {
                    $reasons[] = 'LATER_RETURN_CHARGE';
                }
            }
            foreach ($byRentalCorrection->get($rentalId, collect()) as $correction) {
                if ((string) $correction->opened_at > $end
                    || ($correction->finalized_at !== null && (string) $correction->finalized_at > $end)) {
                    $reasons[] = 'LATER_OPERATIONAL_CORRECTION';
                }
            }

            $asOfStatus = null;
            foreach ($byRentalHistory->get($rentalId, collect()) as $event) {
                if ((string) $event->changed_at <= $end) {
                    if ((string) $event->created_at > $end) {
                        $reasons[] = 'BACKDATED_STATUS_EVENT';
                    }
                    $asOfStatus = (string) $event->to_status;
                }
            }
            if ($asOfStatus === null) {
                if ((string) $rental->created_at > $end || $rental->status === 'draft') {
                    $reasons[] = 'STATUS_HISTORY_MISSING';
                }
                $asOfStatus = $rental->returned_at !== null && (string) $rental->returned_at > $end
                    ? 'active' : (string) $rental->status;
            }
            if ($rental->returned_at !== null && (string) $rental->returned_at > $end
                && in_array($asOfStatus, ['completed', 'returned'], true)) {
                $asOfStatus = 'active';
            }
            if (in_array($asOfStatus, ['draft', 'cancelled', 'void', 'rejected'], true)) {
                continue;
            }
            if ($filters['status'] !== 'all' && $asOfStatus !== $filters['status']) {
                continue;
            }

            $linkedPayments = $byRentalPayment->get($rentalId, collect())
                ->concat($bookingId === null ? collect() : $byBookingPayment->get($bookingId, collect()))
                ->unique('id');
            $paid = 0.0;
            $currentLedgerPaid = 0.0;
            foreach ($linkedPayments as $payment) {
                if ($payment->direction !== 'in' || $payment->type !== 'rental') {
                    continue;
                }
                if ($bookingId !== null && $payment->booking_id !== null
                    && (int) $payment->booking_id === $bookingId
                    && $payment->rental_id !== null && (int) $payment->rental_id !== $rentalId) {
                    $reasons[] = 'CONFLICTING_PAYMENT_LINK';

                    continue;
                }
                $allPaidRefunds = 0.0;
                foreach ($byPaymentRefund->get((int) $payment->id, collect()) as $refund) {
                    if ($refund->status === 'paid') {
                        if ($refund->processed_at === null) {
                            $reasons[] = 'PAID_REFUND_DATE_MISSING';
                        }
                        $allPaidRefunds += (float) $refund->amount;
                    }
                }
                if ($payment->status === 'completed') {
                    $currentLedgerPaid += max(0, (float) $payment->amount - $allPaidRefunds);
                }
                if ((string) $payment->created_at > $end && (string) $payment->paid_at <= $end) {
                    $reasons[] = 'BACKDATED_PAYMENT';
                }
                $voidedLater = $payment->status === 'void' && $payment->voided_at !== null
                    && (string) $payment->voided_at > $end;
                if ($payment->status === 'void' && $payment->voided_at === null) {
                    $reasons[] = 'VOID_DATE_MISSING';
                }
                if ((string) $payment->paid_at > $end
                    || ($payment->status !== 'completed' && ! $voidedLater)) {
                    continue;
                }
                $refundSum = 0.0;
                foreach ($byPaymentRefund->get((int) $payment->id, collect()) as $refund) {
                    if ($refund->processed_at === null) {
                        continue;
                    }
                    if ((string) $refund->created_at > $end && (string) $refund->processed_at <= $end) {
                        $reasons[] = 'BACKDATED_REFUND';
                    }
                    if ($refund->status === 'paid' && (string) $refund->processed_at <= $end) {
                        $refundSum += (float) $refund->amount;
                    } elseif ((string) $refund->processed_at <= $end && $refund->status !== 'paid') {
                        $reasons[] = 'REFUND_PAST_STATE_UNKNOWN';
                    }
                }
                $paid += max(0.0, (float) $payment->amount - $refundSum);
            }
            foreach ($byRentalAdjustment->get($rentalId, collect()) as $adjustment) {
                if ($adjustment->component === 'payment') {
                    $currentLedgerPaid += $adjustment->direction === 'increase'
                        ? (float) $adjustment->amount : -(float) $adjustment->amount;
                }
                if ((string) $adjustment->created_at > $end) {
                    if ($adjustment->component === 'charge') {
                        $reasons[] = 'LATER_FINANCIAL_CHARGE';
                    }

                    continue;
                }
                if ($adjustment->component === 'payment') {
                    $paid += $adjustment->direction === 'increase'
                        ? (float) $adjustment->amount : -(float) $adjustment->amount;
                }
            }

            if ($linkedPayments->isEmpty() && (float) $rental->paid_amount > 0.009) {
                $reasons[] = 'PAYMENT_LEDGER_MISSING';
            }
            if (abs(round($currentLedgerPaid, 2) - (float) $rental->paid_amount) > 0.01) {
                $reasons[] = 'CURRENT_PAYMENT_LEDGER_MISMATCH';
            }
            if ($paid < -0.009) {
                $reasons[] = 'INVALID_PAYMENT_ADJUSTMENTS';
            }

            $reasons = array_values(array_unique($reasons));
            $verified = $reasons === [];
            $balance = $verified ? max(0, round((float) $rental->total_amount - $paid, 2)) : null;
            if ($verified && $balance < 0.01) {
                continue;
            }
            $daysOverdue = CarbonImmutable::parse($dueAt)->lessThan($cutoff)
                ? (int) CarbonImmutable::parse($dueAt)->startOfDay()->diffInDays($cutoff->startOfDay())
                : 0;
            $rows[] = [
                'id' => 'receivable-'.$rentalId,
                'href' => '/rentals/'.$rentalId,
                'values' => [
                    'rental_number' => (string) $rental->rental_number,
                    'customer' => (string) $rental->customer_name,
                    'branch' => (string) $rental->branch_code,
                    'status' => $asOfStatus,
                    'due_at' => $dueAt,
                    'days_overdue' => $daysOverdue,
                    'total_amount' => $verified ? (float) $rental->total_amount : null,
                    'paid_amount' => $verified ? round($paid, 2) : null,
                    'balance_due' => $balance,
                    'deposit_amount' => (float) $rental->deposit_amount,
                    'history_quality' => $verified ? 'Rekonstruksi dari event (perlu rekonsiliasi UAT)' : 'BELUM DAPAT DIVERIFIKASI: '.implode(', ', $reasons),
                ],
            ];
            if ($verified) {
                $verifiedCount++;
                $verifiedTotal += $balance;
                $branchTotals[$branchId] = ($branchTotals[$branchId] ?? 0.0) + $balance;
            } else {
                $unverifiedCount++;
                $branchUnverified[$branchId] = ($branchUnverified[$branchId] ?? 0) + 1;
            }
        }

        return [
            'rows' => $rows,
            'meta' => [
                'as_of' => $cutoff->toDateString(),
                'unverified_count' => $unverifiedCount,
                'verified_count' => $verifiedCount,
                'verified_receivables' => round($verifiedTotal, 2),
                'is_partial' => $unverifiedCount > 0,
            ],
            'branch_totals' => $branchTotals,
            'branch_unverified' => $branchUnverified,
        ];
    }
}
