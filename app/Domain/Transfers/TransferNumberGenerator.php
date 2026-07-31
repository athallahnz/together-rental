<?php

namespace App\Domain\Transfers;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class TransferNumberGenerator
{
    public function next(Branch $origin): string
    {
        $prefix = 'TRF-'.mb_strtoupper($origin->code).'-'.now()->format('ymd');
        $last = DB::table('branch_transfers')
            ->where('from_branch_id', $origin->id)
            ->where('transfer_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->max('transfer_number');
        $sequence = $last === null ? 1 : ((int) mb_substr((string) $last, -4)) + 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
