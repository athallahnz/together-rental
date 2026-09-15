<?php

namespace App\Domain\Finance;

use App\Domain\Rentals\RentalNumberGenerator;
use App\Models\Branch;
use App\Models\FinancialCategory;
use App\Models\OperationalExpense;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class OperationalExpenseManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly PaymentManager $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): OperationalExpense
    {
        $proof = $this->storeProof($data['proof'] ?? null);

        try {
            return DB::transaction(function () use ($data, $actor, $proof): OperationalExpense {
                $branch = Branch::query()->lockForUpdate()->findOrFail((int) $data['branch_id']);
                $this->guardBranch($branch, $actor);
                $category = $this->expenseCategory($branch, (int) $data['financial_category_id']);

                return OperationalExpense::query()->create([
                    'branch_id' => $branch->id,
                    'financial_category_id' => $category->id,
                    'expense_number' => $this->numbers->nextOperationalExpense($branch),
                    'status' => 'recorded',
                    'amount' => (float) $data['amount'],
                    'incurred_at' => $data['incurred_at'],
                    'vendor_name' => $this->nullableString($data['vendor_name'] ?? null),
                    'external_reference' => $this->nullableString($data['external_reference'] ?? null),
                    'notes' => $this->nullableString($data['notes'] ?? null),
                    ...$proof,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);
            }, 3);
        } catch (Throwable $throwable) {
            $this->deleteProof($proof['proof_path'] ?? null);
            throw $throwable;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(
        OperationalExpense $expense,
        array $data,
        User $actor,
    ): OperationalExpense {
        $proof = $this->storeProof($data['proof'] ?? null);
        $oldProof = $expense->proof_path;

        try {
            $updated = DB::transaction(function () use ($expense, $data, $actor, $proof): OperationalExpense {
                $locked = OperationalExpense::query()
                    ->with('branch')
                    ->lockForUpdate()
                    ->findOrFail($expense->id);
                $this->guardBranch($locked->branch, $actor);

                if ($locked->status !== 'recorded') {
                    throw ValidationException::withMessages([
                        'expense' => 'Pengeluaran yang sudah dibayar atau di-void tidak dapat diedit.',
                    ]);
                }
                if ((int) $data['branch_id'] !== (int) $locked->branch_id) {
                    throw ValidationException::withMessages([
                        'branch_id' => 'Cabang pengeluaran yang sudah dibuat tidak dapat dipindahkan.',
                    ]);
                }

                $category = $this->expenseCategory(
                    $locked->branch,
                    (int) $data['financial_category_id'],
                );
                $updates = [
                    'financial_category_id' => $category->id,
                    'amount' => (float) $data['amount'],
                    'incurred_at' => $data['incurred_at'],
                    'vendor_name' => $this->nullableString($data['vendor_name'] ?? null),
                    'external_reference' => $this->nullableString($data['external_reference'] ?? null),
                    'notes' => $this->nullableString($data['notes'] ?? null),
                    'updated_by' => $actor->id,
                ];
                if ($proof !== []) {
                    $updates = [...$updates, ...$proof];
                }

                $locked->forceFill($updates)->save();

                return $locked;
            }, 3);
        } catch (Throwable $throwable) {
            $this->deleteProof($proof['proof_path'] ?? null);
            throw $throwable;
        }

        if ($proof !== [] && is_string($oldProof) && $oldProof !== '') {
            Storage::disk('local')->delete($oldProof);
        }

        return $updated;
    }

    /** @param array<string, mixed> $data */
    public function pay(
        OperationalExpense $expense,
        array $data,
        User $actor,
    ): OperationalExpense {
        return DB::transaction(function () use ($expense, $data, $actor): OperationalExpense {
            $locked = OperationalExpense::query()
                ->with(['branch', 'financialCategory'])
                ->lockForUpdate()
                ->findOrFail($expense->id);
            $this->guardBranch($locked->branch, $actor);

            if ($locked->status === 'paid' && $locked->payment_id !== null) {
                return $locked;
            }
            if ($locked->status !== 'recorded') {
                throw ValidationException::withMessages([
                    'expense' => 'Hanya pengeluaran berstatus recorded yang dapat dibayar.',
                ]);
            }

            $payment = $this->payments->record($locked->branch, [
                'payment_method_id' => $data['payment_method_id'],
                'financial_category_id' => $locked->financial_category_id,
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'direction' => 'out',
                'type' => 'operational_expense',
                'source_context' => 'operational_expense',
                'amount' => (float) $locked->amount,
                'paid_at' => $data['paid_at'],
                'external_reference' => $this->nullableString($data['payment_reference'] ?? null)
                    ?? $locked->external_reference,
                'notes' => $this->nullableString($data['notes'] ?? null)
                    ?? "Pengeluaran operasional {$locked->expense_number}",
            ], $actor);

            $locked->forceFill([
                'payment_method_id' => $payment->payment_method_id,
                'cash_session_id' => $payment->cash_session_id,
                'payment_id' => $payment->id,
                'status' => 'paid',
                'paid_by' => $actor->id,
                'paid_at' => $payment->paid_at,
                'external_reference' => $payment->external_reference ?? $locked->external_reference,
                'updated_by' => $actor->id,
            ])->save();

            return $locked->fresh([
                'branch', 'financialCategory', 'paymentMethod', 'cashSession', 'payment', 'payer',
            ]);
        }, 3);
    }

    public function void(
        OperationalExpense $expense,
        string $reason,
        User $actor,
    ): OperationalExpense {
        $current = OperationalExpense::query()
            ->with('branch')
            ->findOrFail($expense->id);
        $this->guardBranch($current->branch, $actor);

        if ($current->status === 'void') {
            return $current;
        }

        if ($current->payment_id !== null) {
            $payment = Payment::query()->findOrFail($current->payment_id);
            $this->payments->void($payment, $reason, $actor);

            return OperationalExpense::query()->findOrFail($current->id);
        }

        return DB::transaction(function () use ($current, $reason, $actor): OperationalExpense {
            $locked = OperationalExpense::query()->lockForUpdate()->findOrFail($current->id);
            if ($locked->status === 'void') {
                return $locked;
            }
            if ($locked->status !== 'recorded') {
                throw ValidationException::withMessages([
                    'expense' => 'Status pengeluaran tidak dapat di-void.',
                ]);
            }

            $locked->forceFill([
                'status' => 'void',
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $reason,
                'updated_by' => $actor->id,
            ])->save();

            return $locked;
        }, 3);
    }

    private function expenseCategory(Branch $branch, int $categoryId): FinancialCategory
    {
        $category = FinancialCategory::query()
            ->where('company_id', $branch->company_id)
            ->where('type', 'expense')
            ->where('is_active', true)
            ->find($categoryId);

        if ($category === null) {
            throw ValidationException::withMessages([
                'financial_category_id' => 'Kategori pengeluaran tidak tersedia atau bukan tipe expense.',
            ]);
        }

        return $category;
    }

    private function guardBranch(Branch $branch, User $actor): void
    {
        if ($actor->company_id !== $branch->company_id) {
            abort(404);
        }
        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang pengeluaran sedang nonaktif.',
            ]);
        }
        if ($actor->current_branch_id !== $branch->id && ! $actor->hasCompanyScopedRole()) {
            abort(403, 'Aktifkan cabang pengeluaran sebelum melakukan perubahan.');
        }
    }

    /**
     * @return array{proof_path: string, proof_original_name: string, proof_mime_type: string|null, proof_size: int}|array{}
     */
    private function storeProof(mixed $proof): array
    {
        if (! $proof instanceof UploadedFile) {
            return [];
        }

        $path = $proof->store('finance/operational-expenses/'.now()->format('Y/m'), 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'proof' => 'Bukti pengeluaran gagal disimpan.',
            ]);
        }

        $mime = $proof->getMimeType();

        return [
            'proof_path' => $path,
            'proof_original_name' => $proof->getClientOriginalName(),
            'proof_mime_type' => is_string($mime) ? $mime : null,
            'proof_size' => (int) $proof->getSize(),
        ];
    }

    private function deleteProof(mixed $path): void
    {
        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
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
}
