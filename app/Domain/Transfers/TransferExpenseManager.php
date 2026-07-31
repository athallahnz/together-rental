<?php

namespace App\Domain\Transfers;

use App\Domain\Rentals\RentalNumberGenerator;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\BranchTransferExpense;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferExpenseManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly TransferMediaManager $media,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(BranchTransfer $transfer, array $data, User $actor): BranchTransferExpense
    {
        return $this->media->transactional(function () use ($transfer, $data, $actor): BranchTransferExpense {
            $locked = BranchTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->guardExpenseBranch($locked, (int) $data['expense_branch_id'], $actor);

            $expense = BranchTransferExpense::query()->create([
                'branch_transfer_id' => $locked->id,
                ...Arr::only($data, [
                    'expense_branch_id',
                    'expense_type',
                    'estimated_amount',
                    'actual_amount',
                    'vendor_name',
                    'external_reference',
                    'notes',
                ]),
                'financial_category_id' => $this->categoryId($locked, $data['financial_category_id'] ?? null),
                'status' => ((float) ($data['actual_amount'] ?? 0)) > 0 ? 'recorded' : 'estimated',
                'created_by' => $actor->id,
            ]);

            if (($data['proof'] ?? null) instanceof UploadedFile) {
                $this->media->storeDocument(
                    $locked,
                    $data['proof'],
                    'expense',
                    'expense_proof',
                    'gallery',
                    $actor,
                    null,
                    $expense,
                );
            }

            return $expense;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(
        BranchTransfer $transfer,
        BranchTransferExpense $expense,
        array $data,
        User $actor,
    ): BranchTransferExpense {
        return $this->media->transactional(function () use ($transfer, $expense, $data, $actor): BranchTransferExpense {
            $locked = BranchTransferExpense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->guardBelongs($transfer, $locked);
            $this->guardExpenseBranch($transfer, (int) $data['expense_branch_id'], $actor);

            if (in_array($locked->status, ['paid', 'void'], true)) {
                throw ValidationException::withMessages([
                    'expense' => 'Biaya yang sudah dibayar atau dibatalkan tidak dapat diedit.',
                ]);
            }

            $locked->forceFill([
                ...Arr::only($data, [
                    'expense_branch_id',
                    'expense_type',
                    'estimated_amount',
                    'actual_amount',
                    'vendor_name',
                    'external_reference',
                    'notes',
                ]),
                'financial_category_id' => $this->categoryId($transfer, $data['financial_category_id'] ?? null),
                'status' => ((float) ($data['actual_amount'] ?? 0)) > 0 ? 'recorded' : 'estimated',
            ])->save();

            if (($data['proof'] ?? null) instanceof UploadedFile) {
                $this->media->storeDocument(
                    $transfer,
                    $data['proof'],
                    'expense',
                    'expense_proof',
                    'gallery',
                    $actor,
                    null,
                    $locked,
                );
            }

            return $locked;
        });
    }

    /** @param array<string, mixed> $data */
    public function pay(
        BranchTransfer $transfer,
        BranchTransferExpense $expense,
        array $data,
        User $actor,
    ): BranchTransferExpense {
        return $this->media->transactional(function () use ($transfer, $expense, $data, $actor): BranchTransferExpense {
            $locked = BranchTransferExpense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->guardBelongs($transfer, $locked);
            $this->guardExpenseBranch($transfer, $locked->expense_branch_id, $actor);

            if ($locked->status === 'paid' && $locked->payment_id !== null) {
                return $locked;
            }

            if ($locked->status === 'void') {
                throw ValidationException::withMessages([
                    'expense' => 'Biaya yang dibatalkan tidak dapat dibayar.',
                ]);
            }

            $amount = (float) ($data['actual_amount'] ?? $locked->actual_amount);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'actual_amount' => 'Nominal aktual harus lebih dari nol.',
                ]);
            }

            $branch = Branch::query()->lockForUpdate()->findOrFail($locked->expense_branch_id);
            $payment = Payment::query()->create([
                'branch_id' => $branch->id,
                'payment_method_id' => $data['payment_method_id'],
                'financial_category_id' => $locked->financial_category_id,
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'payment_number' => $this->numbers->nextPayment($branch),
                'direction' => 'out',
                'type' => 'transfer_expense',
                'status' => 'completed',
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now(),
                'external_reference' => $data['external_reference'] ?? $locked->external_reference,
                'notes' => $data['notes'] ?? "Biaya transfer {$transfer->transfer_number}",
                'received_by' => $actor->id,
            ]);

            if (! empty($data['cash_session_id'])) {
                $this->recordCashTransaction(
                    (int) $data['cash_session_id'],
                    $payment,
                    $locked,
                    $actor,
                );
            }

            $locked->forceFill([
                'payment_method_id' => $data['payment_method_id'],
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'payment_id' => $payment->id,
                'status' => 'paid',
                'actual_amount' => $amount,
                'paid_at' => $payment->paid_at,
                'external_reference' => $payment->external_reference,
                'paid_by' => $actor->id,
            ])->save();

            if (($data['proof'] ?? null) instanceof UploadedFile) {
                $this->media->storeDocument(
                    $transfer,
                    $data['proof'],
                    'expense',
                    'expense_proof',
                    'gallery',
                    $actor,
                    null,
                    $locked,
                );
            }

            return $locked;
        });
    }

    public function void(
        BranchTransfer $transfer,
        BranchTransferExpense $expense,
        string $reason,
        User $actor,
    ): BranchTransferExpense {
        return DB::transaction(function () use ($transfer, $expense, $reason, $actor): BranchTransferExpense {
            $locked = BranchTransferExpense::query()->lockForUpdate()->findOrFail($expense->id);
            $this->guardBelongs($transfer, $locked);
            $this->guardExpenseBranch($transfer, $locked->expense_branch_id, $actor);

            if ($locked->status === 'void') {
                return $locked;
            }

            if ($locked->payment_id !== null) {
                $payment = Payment::query()->lockForUpdate()->findOrFail($locked->payment_id);
                if ($locked->cash_session_id !== null) {
                    $this->reverseCashTransaction($locked, $payment, $actor, $reason);
                }
                $payment->forceFill([
                    'status' => 'void',
                    'voided_by' => $actor->id,
                    'voided_at' => now(),
                    'void_reason' => $reason,
                ])->save();
            }

            $locked->forceFill([
                'status' => 'void',
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
            ])->save();

            return $locked;
        }, 3);
    }

    private function categoryId(BranchTransfer $transfer, mixed $categoryId): int
    {
        if ($categoryId !== null && $categoryId !== '') {
            return (int) $categoryId;
        }

        $defaultId = DB::table('financial_categories')
            ->where('company_id', $transfer->company_id)
            ->where('code', 'TRANSFER-SHIPPING')
            ->where('type', 'expense')
            ->where('is_active', true)
            ->value('id');

        if ($defaultId === null) {
            throw ValidationException::withMessages([
                'financial_category_id' => 'Kategori biaya transfer belum tersedia. Jalankan seeder foundation.',
            ]);
        }

        return (int) $defaultId;
    }

    private function guardBelongs(BranchTransfer $transfer, BranchTransferExpense $expense): void
    {
        abort_unless($expense->branch_transfer_id === $transfer->id, 404);
    }

    private function guardExpenseBranch(BranchTransfer $transfer, int $branchId, User $actor): void
    {
        if (! in_array($branchId, [$transfer->from_branch_id, $transfer->to_branch_id], true)) {
            throw ValidationException::withMessages([
                'expense_branch_id' => 'Biaya harus dibebankan pada cabang asal atau tujuan.',
            ]);
        }

        if ($actor->current_branch_id === $branchId || $actor->can('transfers.override')) {
            return;
        }

        abort(403, 'Aktifkan cabang penanggung biaya sebelum mencatat pengeluaran.');
    }

    private function reverseCashTransaction(
        BranchTransferExpense $expense,
        Payment $payment,
        User $actor,
        string $reason,
    ): void {
        $cashSessionId = (int) $expense->cash_session_id;
        $session = DB::table('cash_sessions')
            ->where('id', $cashSessionId)
            ->lockForUpdate()
            ->first();
        if ($session === null || $session->status !== 'open') {
            throw ValidationException::withMessages([
                'expense' => 'Biaya kas hanya dapat dibatalkan ketika sesi kas terkait masih terbuka.',
            ]);
        }

        $alreadyReversed = DB::table('cash_transactions')
            ->where('cash_session_id', $cashSessionId)
            ->where('type', 'transfer_expense_void')
            ->where('description', 'like', "%Payment {$payment->payment_number}%")
            ->exists();
        if ($alreadyReversed) {
            return;
        }

        $incoming = (float) DB::table('cash_transactions')
            ->where('cash_session_id', $cashSessionId)
            ->where('direction', 'in')
            ->sum('amount');
        $outgoing = (float) DB::table('cash_transactions')
            ->where('cash_session_id', $cashSessionId)
            ->where('direction', 'out')
            ->sum('amount');
        $balanceAfter = (float) $session->opening_balance + $incoming - $outgoing + (float) $payment->amount;
        $sequence = DB::table('cash_transactions')
            ->where('cash_session_id', $cashSessionId)
            ->lockForUpdate()
            ->count() + 1;

        DB::table('cash_transactions')->insert([
            'cash_session_id' => $cashSessionId,
            'payment_id' => $payment->id,
            'financial_category_id' => $expense->financial_category_id,
            'transaction_number' => 'CTX-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'direction' => 'in',
            'type' => 'transfer_expense_void',
            'amount' => $payment->amount,
            'balance_after' => $balanceAfter,
            'occurred_at' => now(),
            'description' => "Pembalikan Payment {$payment->payment_number}: {$reason}",
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordCashTransaction(
        int $cashSessionId,
        Payment $payment,
        BranchTransferExpense $expense,
        User $actor,
    ): void {
        $session = DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->where('cash_sessions.id', $cashSessionId)
            ->lockForUpdate()
            ->first([
                'cash_sessions.*',
                'cash_registers.branch_id as register_branch_id',
            ]);
        if ($session === null || $session->status !== 'open') {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Sesi kas tidak aktif.',
            ]);
        }
        if ((int) $session->register_branch_id !== $payment->branch_id) {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Sesi kas harus berasal dari cabang penanggung biaya.',
            ]);
        }

        $incoming = (float) DB::table('cash_transactions')
            ->where('cash_session_id', $cashSessionId)
            ->where('direction', 'in')
            ->sum('amount');
        $outgoing = (float) DB::table('cash_transactions')
            ->where('cash_session_id', $cashSessionId)
            ->where('direction', 'out')
            ->sum('amount');
        $balanceAfter = (float) $session->opening_balance + $incoming - $outgoing - (float) $payment->amount;
        $sequence = DB::table('cash_transactions')->where('cash_session_id', $cashSessionId)->lockForUpdate()->count() + 1;

        DB::table('cash_transactions')->insert([
            'cash_session_id' => $cashSessionId,
            'payment_id' => $payment->id,
            'financial_category_id' => $expense->financial_category_id,
            'transaction_number' => 'CTX-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'direction' => 'out',
            'type' => 'transfer_expense',
            'amount' => $payment->amount,
            'balance_after' => $balanceAfter,
            'occurred_at' => $payment->paid_at,
            'description' => $payment->notes,
            'created_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
