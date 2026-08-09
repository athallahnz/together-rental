<?php

namespace App\Domain\Transfers;

use App\Domain\Finance\PaymentManager;
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
        private readonly TransferMediaManager $media,
        private readonly PaymentManager $payments,
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
            $payment = $this->payments->record($branch, [
                'payment_method_id' => $data['payment_method_id'],
                'financial_category_id' => $locked->financial_category_id,
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'direction' => 'out',
                'type' => 'transfer_expense',
                'source_context' => 'transfer_expense',
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now(),
                'external_reference' => $data['external_reference'] ?? $locked->external_reference,
                'notes' => $data['notes'] ?? "Biaya transfer {$transfer->transfer_number}",
            ], $actor);

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
            $current = BranchTransferExpense::query()->findOrFail($expense->id);
            $this->guardBelongs($transfer, $current);
            $this->guardExpenseBranch($transfer, $current->expense_branch_id, $actor);

            if ($current->status === 'void') {
                return $current;
            }

            if ($current->payment_id !== null) {
                $payment = Payment::query()->findOrFail($current->payment_id);
                $this->payments->void($payment, $reason, $actor);
            }

            $locked = BranchTransferExpense::query()
                ->lockForUpdate()
                ->findOrFail($current->id);

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
}
