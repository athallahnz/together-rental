<?php

namespace App\Domain\Bookings;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class BookingNumberGenerator
{
    public function next(Branch $branch): string
    {
        $prefix = 'BKG-'.mb_strtoupper($branch->code).'-'.now()->format('ymd');
        $last = DB::table('bookings')
            ->where('branch_id', $branch->id)
            ->where('booking_number', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->max('booking_number');
        $sequence = $last === null ? 1 : ((int) mb_substr((string) $last, -4)) + 1;

        return $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
