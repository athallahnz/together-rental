<?php

namespace App\Domain\Rentals;

use App\Models\Rental;
use App\Models\RentalFinancialAdjustment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RentalFinancialCorrectionManager
{
    /** @param array<string, mixed> $data */
    public function create(
        Rental $rental,
        array $data,
        User $actor,
    ): RentalFinancialAdjustment {
        return DB::transaction(function () use ($rental, $data, $actor): RentalFinancialAdjustment {
            $locked = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($locked->status, ['returned', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'rental' => 'Koreksi keuangan hanya tersedia untuk rental yang sudah selesai.',
                ]);
            }

            $component = (string) $data['component'];
            $direction = (string) $data['direction'];
            $amount = round((float) $data['amount'], 2);
            $multiplier = $direction === 'increase' ? 1 : -1;
            $changes = $this->changes($locked, $component, $amount * $multiplier);

            foreach ($changes as $attribute => $value) {
                if ($attribute !== 'balance_due' && $value < 0) {
                    throw ValidationException::withMessages([
                        'amount' => 'Nominal koreksi membuat nilai komponen menjadi kurang dari Rp0.',
                    ]);
                }
            }

            $before = (float) $locked->balance_due;
            $locked->forceFill([
                ...$changes,
                'updated_by' => $actor->id,
            ])->save();

            return RentalFinancialAdjustment::query()->create([
                'rental_id' => $locked->id,
                'branch_id' => $locked->branch_id,
                'adjustment_number' => $this->nextNumber($locked),
                'component' => $component,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => (float) $locked->balance_due,
                'reason' => $data['reason'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);
        });
    }

    /** @return array<string, float> */
    private function changes(Rental $rental, string $component, float $signedAmount): array
    {
        return match ($component) {
            'charge' => [
                'total_amount' => round((float) $rental->total_amount + $signedAmount, 2),
                'balance_due' => round((float) $rental->balance_due + $signedAmount, 2),
            ],
            'payment' => [
                'paid_amount' => round((float) $rental->paid_amount + $signedAmount, 2),
                'balance_due' => round((float) $rental->balance_due - $signedAmount, 2),
            ],
            'deposit' => [
                'deposit_amount' => round((float) $rental->deposit_amount + $signedAmount, 2),
                'balance_due' => (float) $rental->balance_due,
            ],
        };
    }

    private function nextNumber(Rental $rental): string
    {
        $prefix = 'ADJ-'.$rental->branch->code.'-'.now()->format('ymd').'-';
        $last = RentalFinancialAdjustment::query()
            ->where('branch_id', $rental->branch_id)
            ->where('adjustment_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('adjustment_number')
            ->value('adjustment_number');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
