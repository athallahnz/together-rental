<?php

namespace App\Domain\Transfers;

use App\Models\Asset;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransferTimelineRecorder
{
    public function transferStatus(
        BranchTransfer $transfer,
        ?string $from,
        string $to,
        ?string $reason,
        User $actor,
        ?int $branchId = null,
    ): void {
        DB::table('status_histories')->insert([
            'subject_type' => BranchTransfer::class,
            'subject_id' => $transfer->id,
            'branch_id' => $branchId ?? $actor->current_branch_id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function asset(
        Asset $asset,
        BranchTransferItem $item,
        string $fromStatus,
        string $toStatus,
        string $fromCondition,
        string $toCondition,
        string $reason,
        User $actor,
        ?int $branchId = null,
    ): void {
        DB::table('asset_status_histories')->insert([
            'asset_id' => $asset->id,
            'branch_id' => $branchId ?? $asset->current_branch_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_condition' => $fromCondition,
            'to_condition' => $toCondition,
            'source_type' => BranchTransferItem::class,
            'source_id' => $item->id,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
