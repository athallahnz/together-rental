<?php

namespace App\Http\Controllers\Transfers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Transfers\TransferDispatchManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\DispatchBranchTransferRequest;
use App\Models\BranchTransfer;
use Illuminate\Http\RedirectResponse;

class BranchTransferDispatchController extends Controller
{
    public function store(
        DispatchBranchTransferRequest $request,
        BranchTransfer $transfer,
        TransferDispatchManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $transfer->status->value;
        $transfer = $manager->dispatch($transfer, $request->validated(), $request->user());
        $recorder->record($request, 'transfer.dispatched', $transfer,
            ['status' => $before],
            [
                'status' => $transfer->status->value,
                'waybill_number' => $transfer->waybill_number,
                'shipped_at' => $transfer->shipped_at,
            ],
            $transfer->from_branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Dispatch berhasil dikonfirmasi. Aset tetap berstatus In Transit.',
        ]);
    }
}
