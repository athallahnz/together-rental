<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;

trait InteractsWithFinance
{
    protected function openCashSession(
        User $user,
        Branch $branch,
        float $openingBalance = 0,
    ): CashSession {
        $register = CashRegister::query()
            ->where('branch_id', $branch->id)
            ->where('is_active', true)
            ->firstOrFail();

        return CashSession::query()->create([
            'cash_register_id' => $register->id,
            'opened_by' => $user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_balance' => $openingBalance,
            'expected_closing_balance' => $openingBalance,
            'difference_amount' => 0,
        ]);
    }
}
