<?php

namespace App\Http\Controllers\Transfers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Transfers\Enums\DiscrepancyResolution;
use App\Domain\Transfers\TransferReceivingManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\ReceiveBranchTransferRequest;
use App\Http\Requests\Transfers\ResolveTransferDiscrepancyRequest;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use Illuminate\Http\RedirectResponse;

class BranchTransferReceivingController extends Controller
{
    public function store(
        ReceiveBranchTransferRequest $request,
        BranchTransfer $transfer,
        TransferReceivingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $before = $transfer->status->value;
        $transfer = $manager->receive($transfer, $request->validated(), $request->user());
        $recorder->record($request, 'transfer.receiving.processed', $transfer,
            ['status' => $before],
            [
                'status' => $transfer->status->value,
                'received_at' => $transfer->received_at,
            ],
            $transfer->to_branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $transfer->status->value === 'completed'
                ? 'Seluruh item berhasil diterima dan transfer selesai.'
                : 'Penerimaan tersimpan. Item tersisa atau discrepancy masih perlu ditindaklanjuti.',
        ]);
    }

    public function resolve(
        ResolveTransferDiscrepancyRequest $request,
        BranchTransfer $transfer,
        BranchTransferItem $item,
        TransferReceivingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        abort_unless($item->branch_transfer_id === $transfer->id, 404);
        $transfer = $manager->resolve(
            $transfer,
            $item,
            DiscrepancyResolution::from($request->validated('resolution_action')),
            $request->validated('notes'),
            $request->user(),
        );
        $recorder->record($request, 'transfer.discrepancy.resolved', $transfer, null, [
            'item_id' => $item->id,
            'resolution_action' => $request->validated('resolution_action'),
            'status' => $transfer->status->value,
        ], $transfer->to_branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Discrepancy berhasil diselesaikan.',
        ]);
    }
}
