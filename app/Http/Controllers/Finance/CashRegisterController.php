<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\FinanceMasterManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\SaveCashRegisterRequest;
use App\Models\CashRegister;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CashRegisterController extends Controller
{
    public function store(
        SaveCashRegisterRequest $request,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $register = $manager->createCashRegister($request->validated(), $request->user());
        $recorder->record(
            $request,
            'finance.cash_register.created',
            $register,
            null,
            $register->toArray(),
            $register->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kasir {$register->code} berhasil ditambahkan.",
        ]);
    }

    public function update(
        SaveCashRegisterRequest $request,
        CashRegister $cashRegister,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $cashRegister->toArray();
        $register = $manager->updateCashRegister(
            $cashRegister,
            $request->validated(),
            $request->user(),
        );
        $recorder->record(
            $request,
            'finance.cash_register.updated',
            $register,
            $before,
            $register->toArray(),
            $register->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kasir {$register->code} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(
        Request $request,
        CashRegister $cashRegister,
        FinanceMasterManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('finance.cash_registers.manage');
        $before = ['is_active' => $cashRegister->is_active];
        $register = $manager->toggleCashRegister($cashRegister, $request->user());
        $recorder->record(
            $request,
            $register->is_active
                ? 'finance.cash_register.activated'
                : 'finance.cash_register.deactivated',
            $register,
            $before,
            ['is_active' => $register->is_active],
            $register->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Kasir {$register->code} berhasil "
                .($register->is_active ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }
}
