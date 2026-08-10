<?php

namespace App\Domain\Rentals;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class RentalNumberGenerator
{
    public function nextRental(Branch $branch): string
    {
        return $this->next('rentals', 'rental_number', 'RNT', $branch);
    }

    public function nextExtension(Branch $branch): string
    {
        return $this->next('rental_extensions', 'extension_number', 'EXT', $branch);
    }

    public function nextPayment(Branch $branch): string
    {
        return $this->next('payments', 'payment_number', 'PAY', $branch);
    }

    public function nextReturn(Branch $branch): string
    {
        return $this->next('rental_returns', 'return_number', 'RTN', $branch);
    }

    public function nextRefund(Branch $branch): string
    {
        return $this->next('refunds', 'refund_number', 'RFD', $branch);
    }

    private function next(string $table, string $column, string $label, Branch $branch): string
    {
        $prefix = $label.'-'.mb_strtoupper($branch->code).'-'.now()->format('ymd');
        $last = DB::table($table)
            ->where('branch_id', $branch->id)
            ->where($column, 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->max($column);
        $sequence = $last === null ? 1 : ((int) mb_substr((string) $last, -4)) + 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
