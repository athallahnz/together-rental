<?php

namespace App\Domain\Finance;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashSessionManager
{
    /** @param array<string, mixed> $data */
    public function open(CashRegister $register, array $data, User $actor): CashSession
    {
        return DB::transaction(function () use ($register, $data, $actor): CashSession {
            $locked = CashRegister::query()->with('branch')->lockForUpdate()->findOrFail($register->id);
            $this->guardBranch($locked, $actor);

            if (! $locked->is_active) {
                throw ValidationException::withMessages(['cash_register' => 'Kasir sedang tidak aktif.']);
            }
            if (CashSession::query()->where('cash_register_id', $locked->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['cash_register' => 'Kasir ini masih memiliki sesi terbuka.']);
            }

            return CashSession::query()->create([
                'cash_register_id' => $locked->id,
                'opened_by' => $actor->id,
                'status' => 'open',
                'opened_at' => now(),
                'opening_balance' => $data['opening_balance'],
                'expected_closing_balance' => $data['opening_balance'],
                'difference_amount' => 0,
                'opening_notes' => $data['opening_notes'] ?? null,
            ]);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function close(CashSession $session, array $data, User $actor): CashSession
    {
        return DB::transaction(function () use ($session, $data, $actor): CashSession {
            $locked = CashSession::query()->with('register.branch')->lockForUpdate()->findOrFail($session->id);
            $this->guardBranch($locked->register, $actor);

            if ($locked->status !== 'open') {
                throw ValidationException::withMessages(['cash_session' => 'Sesi kas sudah ditutup.']);
            }

            $incoming = (float) CashTransaction::query()
                ->where('cash_session_id', $locked->id)->where('direction', 'in')->sum('amount');
            $outgoing = (float) CashTransaction::query()
                ->where('cash_session_id', $locked->id)->where('direction', 'out')->sum('amount');
            $expected = (float) $locked->opening_balance + $incoming - $outgoing;
            $actual = (float) $data['actual_closing_balance'];

            $locked->forceFill([
                'closed_by' => $actor->id,
                'status' => 'closed',
                'closed_at' => now(),
                'expected_closing_balance' => $expected,
                'actual_closing_balance' => $actual,
                'difference_amount' => $actual - $expected,
                'closing_notes' => $data['closing_notes'] ?? null,
            ])->save();

            return $locked;
        }, 3);
    }

    private function guardBranch(CashRegister $register, User $actor): void
    {
        if ($actor->company_id !== $register->branch->company_id) {
            abort(404);
        }
        if ($actor->current_branch_id !== $register->branch_id && ! $actor->hasCompanyScopedRole()) {
            abort(403, 'Aktifkan cabang kasir sebelum mengelola sesi kas.');
        }
    }
}
