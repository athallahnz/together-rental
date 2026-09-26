<?php

namespace App\Domain\Finance;

use App\Domain\Bookings\BookingManager;
use App\Domain\Rentals\RentalNumberGenerator;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly RefundEligibility $eligibility,
        private readonly CashLedger $cashLedger,
        private readonly BookingPaymentSettlement $settlement,
        private readonly BookingManager $bookings,
    ) {}

    /** @param array<string, mixed> $data */
    public function request(Payment $payment, array $data, User $actor): Refund
    {
        return DB::transaction(function () use ($payment, $data, $actor): Refund {
            $locked = Payment::query()
                ->with('branch')
                ->lockForUpdate()
                ->findOrFail($payment->id);
            $this->guardBranch($locked->branch, $actor);

            $eligibility = $this->eligibility->forPayment($locked, $actor);
            if (! $eligibility['allowed']) {
                throw ValidationException::withMessages([
                    $eligibility['field'] => (string) $eligibility['reason'],
                ]);
            }

            $amount = round((float) ($data['amount'] ?? 0), 2);
            if ($amount <= 0 || $amount > $eligibility['refundable_amount']) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal refund harus lebih dari nol dan tidak boleh melebihi sisa refundable.',
                ]);
            }

            $bookingDp = $locked->booking_id !== null
                && $locked->source_context === 'booking' && $locked->type === 'rental';
            $purpose = $this->nullableString($data['purpose'] ?? null);
            if ($bookingDp && ! in_array($purpose, Refund::BOOKING_PURPOSES, true)) {
                throw ValidationException::withMessages([
                    'purpose' => 'Pilih tujuan refund DP: Pembatalan Booking atau Koreksi Pembayaran.',
                ]);
            }
            if (! $bookingDp && $purpose !== null) {
                throw ValidationException::withMessages([
                    'purpose' => 'Tujuan refund DP hanya tersedia untuk pembayaran booking.',
                ]);
            }
            if ($purpose === Refund::PURPOSE_BOOKING_CANCELLATION) {
                $booking = Booking::query()->lockForUpdate()->findOrFail($locked->booking_id);
                $this->assertBookingMayBeCancelled($booking, $locked->branch_id);
            }

            $method = PaymentMethod::query()
                ->where('company_id', $locked->branch->company_id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->find((int) ($data['payment_method_id'] ?? 0));
            if ($method === null) {
                throw ValidationException::withMessages([
                    'payment_method_id' => 'Metode pengembalian dana tidak tersedia.',
                ]);
            }

            return Refund::query()->create([
                'branch_id' => $locked->branch_id,
                'payment_id' => $locked->id,
                'booking_id' => $locked->booking_id,
                'rental_id' => $locked->rental_id,
                'payment_method_id' => $method->id,
                'refund_number' => $this->numbers->nextRefund($locked->branch),
                'refund_type' => abs($amount - $eligibility['refundable_amount']) < 0.005
                    ? 'full'
                    : 'partial',
                'purpose' => $purpose,
                'amount' => $amount,
                'status' => 'requested',
                'reason' => $this->requiredString($data['reason'] ?? null, 'reason'),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'requested_by' => $actor->id,
            ]);
        }, 3);
    }

    public function approve(Refund $refund, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $actor): Refund {
            $locked = $this->lock($refund, $actor);
            $this->guardStatus($locked, 'requested');
            $this->guardApproverSeparation($locked, $actor);

            // Refund approval and stock release are atomic; payout is a later action.
            if ($locked->purpose === Refund::PURPOSE_BOOKING_CANCELLATION) {
                if (! $actor->can('bookings.cancel')) {
                    throw ValidationException::withMessages([
                        'refund' => 'Persetujuan refund pembatalan memerlukan izin bookings.cancel.',
                    ]);
                }

                $booking = Booking::query()->lockForUpdate()->findOrFail($locked->booking_id);
                $this->assertBookingMayBeCancelled($booking, $locked->branch_id);

                if ($booking->status !== 'cancelled') {
                    $this->bookings->cancel(
                        $booking,
                        "Pembatalan melalui refund {$locked->refund_number}: {$locked->reason}",
                        $actor,
                    );
                }
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            return $locked;
        }, 3);
    }

    private function assertBookingMayBeCancelled(Booking $booking, int $branchId): void
    {
        if ((int) $booking->branch_id !== $branchId
            || Rental::query()->where('booking_id', $booking->id)->exists()
            || ! in_array($booking->status, [...Booking::ACTIVE_STATUSES, 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'purpose' => 'Pembatalan melalui refund hanya untuk booking belum checkout di cabang yang sama.',
            ]);
        }
    }

    public function reject(Refund $refund, string $reason, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $reason, $actor): Refund {
            $locked = $this->lock($refund, $actor);
            $this->guardStatus($locked, 'requested');
            $this->guardApproverSeparation($locked, $actor);

            $locked->forceFill([
                'status' => 'rejected',
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function process(Refund $refund, array $data, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $data, $actor): Refund {
            $locked = $this->lock($refund, $actor, ['paymentMethod']);
            $this->guardStatus($locked, 'approved');

            $externalReference = $this->nullableString($data['external_reference'] ?? null);
            $cashSession = null;
            if ($locked->paymentMethod->type === 'cash') {
                $cashSession = $this->cashSession(
                    $locked->branch,
                    $data['cash_session_id'] ?? null,
                );
            } elseif ($externalReference === null) {
                throw ValidationException::withMessages([
                    'external_reference' => 'Referensi transaksi wajib diisi untuk refund non-tunai.',
                ]);
            }

            $proofPath = $this->requiredString($data['proof_path'] ?? null, 'proof');
            $locked->forceFill([
                'cash_session_id' => $cashSession?->id,
                'external_reference' => $externalReference,
                'proof_path' => $proofPath,
                'proof_original_name' => $this->requiredString(
                    $data['proof_original_name'] ?? null,
                    'proof',
                ),
                'proof_mime_type' => $this->nullableString($data['proof_mime_type'] ?? null),
                'proof_size' => (int) ($data['proof_size'] ?? 0),
                'notes' => $this->nullableString($data['notes'] ?? null) ?? $locked->notes,
            ])->save();

            if ($cashSession !== null) {
                $this->cashLedger->recordRefund($locked, $cashSession, $actor);
            }

            $locked->forceFill([
                'status' => 'paid',
                'processed_by' => $actor->id,
                'processed_at' => now(),
            ])->save();

            // Do not rewrite the original payment or contract. Reconcile live balances
            // only when payout is confirmed, in the same transaction as the cash ledger.
            $this->applyPaidRefund($locked);

            return $locked->load(['paymentMethod', 'cashSession.register']);
        }, 3);
    }

    /** Reconcile financial aggregates after one approved refund becomes paid. */
    private function applyPaidRefund(Refund $refund): void
    {
        $payment = Payment::query()->findOrFail($refund->payment_id);
        if ($payment->status !== 'completed' || $payment->direction !== 'in') {
            throw ValidationException::withMessages([
                'payment' => 'Payment sumber refund tidak lagi merupakan penerimaan yang valid.',
            ]);
        }

        $booking = $payment->booking_id === null
            ? null
            : Booking::query()->lockForUpdate()->findOrFail($payment->booking_id);
        $rental = $payment->rental_id !== null
            ? Rental::query()->lockForUpdate()->findOrFail($payment->rental_id)
            : ($booking === null ? null : Rental::query()
                ->where('booking_id', $booking->id)->lockForUpdate()->first());

        if (($booking !== null && (int) $booking->branch_id !== (int) $refund->branch_id)
            || ($rental !== null && (int) $rental->branch_id !== (int) $refund->branch_id)) {
            throw ValidationException::withMessages([
                'payment' => 'Cabang transaksi refund tidak konsisten dengan dokumen sumber.',
            ]);
        }

        $amount = round((float) $refund->amount, 2);

        if ($booking !== null && $payment->type === 'deposit') {
            $booking->forceFill([
                'deposit_paid' => $this->settlement->net($booking, 'deposit'),
            ])->save();
        }

        if ($rental === null || ! in_array($payment->type, ['rental', 'deposit'], true)) {
            return;
        }

        if ($payment->type === 'deposit') {
            if ((float) $rental->deposit_amount + 0.009 < $amount) {
                throw ValidationException::withMessages([
                    'refund' => 'Saldo deposit rental lebih kecil dari nominal refund. Periksa koreksi sebelumnya.',
                ]);
            }
            $rental->forceFill([
                'deposit_amount' => round((float) $rental->deposit_amount - $amount, 2),
            ])->save();

            return;
        }

        if ((float) $rental->paid_amount + 0.009 < $amount) {
            throw ValidationException::withMessages([
                'refund' => 'Saldo pembayaran rental lebih kecil dari nominal refund. Periksa koreksi sebelumnya.',
            ]);
        }
        $updates = [
            'paid_amount' => round((float) $rental->paid_amount - $amount, 2),
            'balance_due' => round((float) $rental->balance_due + $amount, 2),
        ];
        if ($payment->source_context === 'booking') {
            $updates['booking_payment_amount'] = max(0, round(
                (float) $rental->booking_payment_amount - $amount, 2,
            ));
        }
        $rental->forceFill($updates)->save();
    }

    public function cancel(Refund $refund, string $reason, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $reason, $actor): Refund {
            $locked = $this->lock($refund, $actor);
            if (! in_array($locked->status, ['requested', 'approved'], true)) {
                throw ValidationException::withMessages([
                    'refund' => 'Hanya refund requested atau approved yang dapat dibatalkan.',
                ]);
            }

            $locked->forceFill([
                'status' => 'cancelled',
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ])->save();

            return $locked;
        }, 3);
    }

    /**
     * @param  list<string>  $with
     */
    private function lock(Refund $refund, User $actor, array $with = []): Refund
    {
        $locked = Refund::query()
            ->with(['branch', ...$with])
            ->lockForUpdate()
            ->findOrFail($refund->id);
        $this->guardBranch($locked->branch, $actor);

        return $locked;
    }

    private function cashSession(Branch $branch, mixed $cashSessionId): CashSession
    {
        if ($cashSessionId === null || $cashSessionId === '') {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Sesi kas aktif wajib dipilih untuk refund tunai.',
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
                'cash_session_id' => 'Sesi kas tidak aktif atau bukan milik cabang refund.',
            ]);
        }

        return $session;
    }

    private function guardStatus(Refund $refund, string $status): void
    {
        if ($refund->status !== $status) {
            throw ValidationException::withMessages([
                'refund' => "Refund harus berstatus {$status} untuk aksi ini.",
            ]);
        }
    }

    private function guardApproverSeparation(Refund $refund, User $actor): void
    {
        if ((int) $refund->requested_by === (int) $actor->id) {
            throw ValidationException::withMessages([
                'refund' => 'Pengaju refund tidak boleh menyetujui atau menolak pengajuannya sendiri.',
            ]);
        }
    }

    private function guardBranch(Branch $branch, User $actor): void
    {
        if ($actor->company_id !== $branch->company_id) {
            abort(404);
        }
        if ($actor->current_branch_id !== $branch->id && ! $actor->hasCompanyScopedRole()) {
            abort(403, 'Aktifkan cabang refund sebelum menjalankan aksi ini.');
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
