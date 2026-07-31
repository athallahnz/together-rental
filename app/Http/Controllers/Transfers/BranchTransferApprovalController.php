<?php

namespace App\Http\Controllers\Transfers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Transfers\Enums\ApprovalDecision;
use App\Domain\Transfers\Enums\ApprovalSide;
use App\Domain\Transfers\TransferApprovalManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfers\DecideBranchTransferApprovalRequest;
use App\Models\BranchTransfer;
use Illuminate\Http\RedirectResponse;

class BranchTransferApprovalController extends Controller
{
    public function store(
        DecideBranchTransferApprovalRequest $request,
        BranchTransfer $transfer,
        TransferApprovalManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        abort_unless(
            $transfer->revision_number === $request->integer('revision_number'),
            409,
            'Revisi transfer telah berubah.',
        );
        $before = $transfer->status->value;
        $transfer = $manager->decide(
            $transfer,
            ApprovalSide::from($request->validated('side')),
            ApprovalDecision::from($request->validated('decision')),
            $request->validated('notes'),
            $request->user(),
        );
        $recorder->record($request, 'transfer.approval.decided', $transfer,
            ['status' => $before],
            [
                'status' => $transfer->status->value,
                'side' => $request->validated('side'),
                'decision' => $request->validated('decision'),
                'revision_number' => $transfer->revision_number,
            ],
            $request->user()->current_branch_id);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $request->validated('decision') === 'approved'
                ? 'Persetujuan berhasil dicatat.'
                : 'Transfer ditolak dan alasan tersimpan.',
        ]);
    }
}
