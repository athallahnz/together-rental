<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Customers\LoyaltyManager;
use App\Http\Requests\AdjustLoyaltyRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CustomerLoyaltyController extends Controller
{
    public function store(
        AdjustLoyaltyRequest $request,
        Customer $customer,
        LoyaltyManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $transaction = DB::transaction(function () use (
            $customer,
            $manager,
            $recorder,
            $request,
        ) {
            $transaction = $manager->adjust(
                $customer,
                $request->user(),
                $request->validated('type'),
                $request->integer('points'),
                $request->validated('description'),
            );
            $recorder->record(
                $request,
                'customer.loyalty_adjusted',
                $transaction,
                null,
                [
                    'customer_id' => $customer->id,
                    'type' => $transaction->type,
                    'points' => $transaction->points,
                    'balance_after' => $transaction->balance_after,
                ],
                $request->user()->current_branch_id,
            );

            return $transaction;
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Transaksi loyalty {$transaction->points} poin berhasil disimpan.",
        ]);
    }
}
