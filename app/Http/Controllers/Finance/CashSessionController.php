<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\CashSessionManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CloseCashSessionRequest;
use App\Http\Requests\Finance\OpenCashSessionRequest;
use App\Models\CashRegister;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;

class CashSessionController extends Controller
{
    public function store(
        OpenCashSessionRequest $request,
        CashRegister $cashRegister,
        CashSessionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $session = $manager->open($cashRegister, $request->validated(), $request->user());
        $recorder->record($request, 'cash.session.opened', $session, null, [
            'cash_register_id' => $session->cash_register_id,
            'opening_balance' => $session->opening_balance,
        ], $cashRegister->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Sesi kas berhasil dibuka.']);
    }

    public function close(
        CloseCashSessionRequest $request,
        CashSession $cashSession,
        CashSessionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $cashSession->only(['status', 'expected_closing_balance']);
        $session = $manager->close($cashSession, $request->validated(), $request->user());
        $recorder->record($request, 'cash.session.closed', $session, $before, [
            'status' => $session->status,
            'expected_closing_balance' => $session->expected_closing_balance,
            'actual_closing_balance' => $session->actual_closing_balance,
            'difference_amount' => $session->difference_amount,
        ], $session->register->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Sesi kas berhasil ditutup.']);
    }
}
