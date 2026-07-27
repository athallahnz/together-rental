<?php

namespace App\Domain\Customers;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class CustomerNumberGenerator
{
    public function next(Branch $branch): string
    {
        $now = now();
        $scope = [
            'branch_id' => $branch->id,
            'document_type' => 'customer',
            'year' => (int) $now->format('Y'),
            'month' => 0,
        ];
        DB::table('number_sequences')->insertOrIgnore([
            ...$scope,
            'prefix' => "{$branch->code}-CUS",
            'last_number' => 0,
            'padding' => 6,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sequence = DB::table('number_sequences')
            ->where($scope)
            ->lockForUpdate()
            ->firstOrFail();

        $next = (int) $sequence->last_number + 1;
        DB::table('number_sequences')
            ->where('id', $sequence->id)
            ->update([
                'prefix' => "{$branch->code}-CUS",
                'last_number' => $next,
                'updated_at' => $now,
            ]);

        return sprintf(
            '%s-%d-%0'.$sequence->padding.'d',
            "{$branch->code}-CUS",
            (int) $now->format('Y'),
            $next,
        );
    }
}
