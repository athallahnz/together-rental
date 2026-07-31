<?php

namespace App\Http\Controllers\Transfers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Transfers\TransferExpenseManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\PayBranchTransferExpenseRequest;
use App\Http\Requests\Transfers\SaveBranchTransferExpenseRequest;
use App\Http\Requests\Transfers\VoidBranchTransferExpenseRequest;
use App\Models\BranchTransfer;
use App\Models\BranchTransferExpense;
use Illuminate\Http\RedirectResponse;

class BranchTransferExpenseController extends Controller
{
    public function store(
        SaveBranchTransferExpenseRequest $request,
        BranchTransfer $transfer,
        TransferExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $expense = $manager->create($transfer, $request->validated(), $request->user());
        $recorder->record($request, 'transfer.expense.created', $expense, null, [
            'transfer_id' => $transfer->id,
            'status' => $expense->status,
            'actual_amount' => $expense->actual_amount,
        ], $expense->expense_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Biaya transfer berhasil dicatat.']);
    }

    public function update(
        SaveBranchTransferExpenseRequest $request,
        BranchTransfer $transfer,
        BranchTransferExpense $expense,
        TransferExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $expense->only(['status', 'estimated_amount', 'actual_amount']);
        $expense = $manager->update($transfer, $expense, $request->validated(), $request->user());
        $recorder->record($request, 'transfer.expense.updated', $expense, $before,
            $expense->only(['status', 'estimated_amount', 'actual_amount']),
            $expense->expense_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Biaya transfer diperbarui.']);
    }

    public function pay(
        PayBranchTransferExpenseRequest $request,
        BranchTransfer $transfer,
        BranchTransferExpense $expense,
        TransferExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $expense = $manager->pay($transfer, $expense, $request->validated(), $request->user());
        $recorder->record($request, 'transfer.expense.paid', $expense, null, [
            'status' => $expense->status,
            'payment_id' => $expense->payment_id,
            'actual_amount' => $expense->actual_amount,
        ], $expense->expense_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Pembayaran biaya transfer berhasil dicatat.']);
    }

    public function void(
        VoidBranchTransferExpenseRequest $request,
        BranchTransfer $transfer,
        BranchTransferExpense $expense,
        TransferExpenseManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $expense = $manager->void($transfer, $expense, $request->validated('reason'), $request->user());
        $recorder->record($request, 'transfer.expense.voided', $expense, null, [
            'status' => $expense->status,
            'reason' => $request->validated('reason'),
        ], $expense->expense_branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Biaya transfer dibatalkan tanpa menghapus histori.']);
    }
}
