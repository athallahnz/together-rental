<?php

namespace App\Domain\Customers;

use App\Models\Customer;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class LoyaltyManager
{
    public function adjust(
        Customer $customer,
        User $actor,
        string $type,
        int $requestedPoints,
        string $description,
    ): LoyaltyTransaction {
        $account = LoyaltyAccount::query()
            ->where('customer_id', $customer->id)
            ->lockForUpdate()
            ->first();

        if ($account === null) {
            $account = LoyaltyAccount::query()->create([
                'company_id' => $customer->company_id,
                'customer_id' => $customer->id,
                'points_balance' => 0,
                'lifetime_points' => 0,
                'tier' => 'regular',
                'is_active' => true,
            ]);
        }

        if (! $account->is_active) {
            throw ValidationException::withMessages([
                'loyalty' => 'Akun loyalty pelanggan sedang tidak aktif.',
            ]);
        }

        $points = match ($type) {
            'earn' => abs($requestedPoints),
            'redeem' => -abs($requestedPoints),
            default => $requestedPoints,
        };
        $balanceAfter = $account->points_balance + $points;

        if ($balanceAfter < 0) {
            throw ValidationException::withMessages([
                'points' => 'Saldo poin tidak mencukupi untuk transaksi ini.',
            ]);
        }

        $lifetimePoints = $account->lifetime_points + max($points, 0);
        $account->update([
            'points_balance' => $balanceAfter,
            'lifetime_points' => $lifetimePoints,
            'tier' => $this->tier($lifetimePoints),
        ]);

        return $account->transactions()->create([
            'branch_id' => $actor->current_branch_id,
            'type' => $type,
            'points' => $points,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'occurred_at' => now(),
            'created_by' => $actor->id,
        ]);
    }

    private function tier(int $lifetimePoints): string
    {
        return match (true) {
            $lifetimePoints >= 10_000 => 'platinum',
            $lifetimePoints >= 5_000 => 'gold',
            $lifetimePoints >= 1_000 => 'silver',
            default => 'regular',
        };
    }
}
