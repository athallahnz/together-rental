<?php

namespace App\Domain\Finance;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\FinancialCategory;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceMasterManager
{
    private const CORE_CATEGORY_CODES = [
        'RENTAL',
        'DEPOSIT',
        'LATE-FEE',
        'DAMAGE',
        'REFUND',
        'OPERATING',
        'TRANSFER-SHIPPING',
    ];

    /** @param array<string, mixed> $data */
    public function createPaymentMethod(array $data, User $actor): PaymentMethod
    {
        return DB::transaction(fn (): PaymentMethod => PaymentMethod::query()->create([
            ...$data,
            'company_id' => $actor->company_id,
            'is_active' => true,
        ]), 3);
    }

    /** @param array<string, mixed> $data */
    public function updatePaymentMethod(
        PaymentMethod $method,
        array $data,
        User $actor,
    ): PaymentMethod {
        return DB::transaction(function () use ($method, $data, $actor): PaymentMethod {
            $locked = PaymentMethod::query()->lockForUpdate()->findOrFail($method->id);
            $this->guardCompany($locked->company_id, $actor);

            if ($this->paymentMethodIsUsed($locked)
                && ($locked->code !== $data['code'] || $locked->type !== $data['type'])) {
                throw ValidationException::withMessages([
                    'code' => 'Kode dan tipe metode yang sudah dipakai transaksi tidak dapat diubah.',
                ]);
            }

            $locked->update($data);

            return $locked;
        }, 3);
    }

    public function togglePaymentMethod(PaymentMethod $method, User $actor): PaymentMethod
    {
        return DB::transaction(function () use ($method, $actor): PaymentMethod {
            $locked = PaymentMethod::query()->lockForUpdate()->findOrFail($method->id);
            $this->guardCompany($locked->company_id, $actor);

            if ($locked->is_active && $locked->type === 'cash' && $this->hasOpenCashSession($actor)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Metode tunai tidak dapat dinonaktifkan selama masih ada sesi kas terbuka.',
                ]);
            }

            $locked->update(['is_active' => ! $locked->is_active]);

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createCategory(array $data, User $actor): FinancialCategory
    {
        return DB::transaction(fn (): FinancialCategory => FinancialCategory::query()->create([
            ...$data,
            'company_id' => $actor->company_id,
            'is_active' => true,
        ]), 3);
    }

    /** @param array<string, mixed> $data */
    public function updateCategory(
        FinancialCategory $category,
        array $data,
        User $actor,
    ): FinancialCategory {
        return DB::transaction(function () use ($category, $data, $actor): FinancialCategory {
            $locked = FinancialCategory::query()->lockForUpdate()->findOrFail($category->id);
            $this->guardCompany($locked->company_id, $actor);

            if ($this->categoryIsUsed($locked)
                && ($locked->code !== $data['code'] || $locked->type !== $data['type'])) {
                throw ValidationException::withMessages([
                    'code' => 'Kode dan tipe kategori yang sudah dipakai transaksi tidak dapat diubah.',
                ]);
            }

            $locked->update($data);

            return $locked;
        }, 3);
    }

    public function toggleCategory(FinancialCategory $category, User $actor): FinancialCategory
    {
        return DB::transaction(function () use ($category, $actor): FinancialCategory {
            $locked = FinancialCategory::query()->lockForUpdate()->findOrFail($category->id);
            $this->guardCompany($locked->company_id, $actor);

            if ($locked->is_active && in_array($locked->code, self::CORE_CATEGORY_CODES, true)) {
                throw ValidationException::withMessages([
                    'financial_category' => 'Kategori inti sistem tidak dapat dinonaktifkan karena masih digunakan workflow Finance.',
                ]);
            }

            $locked->update(['is_active' => ! $locked->is_active]);

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createCashRegister(array $data, User $actor): CashRegister
    {
        return DB::transaction(function () use ($data, $actor): CashRegister {
            $branch = Branch::query()->lockForUpdate()->findOrFail((int) $data['branch_id']);
            $this->guardBranch($branch, $actor);

            return CashRegister::query()->create([
                'branch_id' => $branch->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'is_active' => true,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateCashRegister(
        CashRegister $register,
        array $data,
        User $actor,
    ): CashRegister {
        return DB::transaction(function () use ($register, $data, $actor): CashRegister {
            $locked = CashRegister::query()->with('branch')->lockForUpdate()->findOrFail($register->id);
            $this->guardBranch($locked->branch, $actor);

            if ((int) $data['branch_id'] !== (int) $locked->branch_id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Kasir yang sudah dibuat tidak dapat dipindahkan ke cabang lain.',
                ]);
            }
            if ($locked->sessions()->exists() && $locked->code !== $data['code']) {
                throw ValidationException::withMessages([
                    'code' => 'Kode kasir yang sudah memiliki histori sesi tidak dapat diubah.',
                ]);
            }

            $locked->update([
                'code' => $data['code'],
                'name' => $data['name'],
            ]);

            return $locked;
        }, 3);
    }

    public function toggleCashRegister(CashRegister $register, User $actor): CashRegister
    {
        return DB::transaction(function () use ($register, $actor): CashRegister {
            $locked = CashRegister::query()->with('branch')->lockForUpdate()->findOrFail($register->id);
            $this->guardBranch($locked->branch, $actor);

            if ($locked->is_active && $locked->sessions()->where('status', 'open')->exists()) {
                throw ValidationException::withMessages([
                    'cash_register' => 'Kasir tidak dapat dinonaktifkan selama sesi kas masih terbuka.',
                ]);
            }

            $locked->update(['is_active' => ! $locked->is_active]);

            return $locked;
        }, 3);
    }

    private function paymentMethodIsUsed(PaymentMethod $method): bool
    {
        return DB::table('payments')->where('payment_method_id', $method->id)->exists()
            || DB::table('refunds')->where('payment_method_id', $method->id)->exists();
    }

    private function categoryIsUsed(FinancialCategory $category): bool
    {
        return DB::table('payments')->where('financial_category_id', $category->id)->exists()
            || DB::table('cash_transactions')->where('financial_category_id', $category->id)->exists()
            || DB::table('branch_transfer_expenses')->where('financial_category_id', $category->id)->exists();
    }

    private function hasOpenCashSession(User $actor): bool
    {
        return DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->join('branches', 'branches.id', '=', 'cash_registers.branch_id')
            ->where('branches.company_id', $actor->company_id)
            ->where('cash_sessions.status', 'open')
            ->exists();
    }

    private function guardCompany(int $companyId, User $actor): void
    {
        if ($actor->company_id !== $companyId) {
            abort(404);
        }
    }

    private function guardBranch(Branch $branch, User $actor): void
    {
        $this->guardCompany($branch->company_id, $actor);

        if (! $branch->is_active) {
            throw ValidationException::withMessages([
                'branch_id' => 'Cabang kasir sedang nonaktif.',
            ]);
        }
        if ($actor->current_branch_id !== $branch->id && ! $actor->hasCompanyScopedRole()) {
            abort(403, 'Aktifkan cabang kasir sebelum mengelola master kasir.');
        }
    }
}
