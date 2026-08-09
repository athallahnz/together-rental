<?php

namespace App\Domain\Finance;

use App\Domain\Rentals\RentalNumberGenerator;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly RefundEligibility $eligibility,
        private readonly CashLedger $cashLedger,
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

            $locked->forceFill([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            return $locked;
        }, 3);
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

            return $locked->load(['paymentMethod', 'cashSession.register']);
        }, 3);
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
