<?php

namespace App\Domain\Transfers;

use App\Domain\Transfers\Enums\ApprovalDecision;
use App\Domain\Transfers\Enums\ApprovalSide;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\BranchTransfer;
use App\Models\BranchTransferApproval;
use App\Models\BranchTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferApprovalManager
{
    public function __construct(
        private readonly TransferSnapshotFactory $snapshots,
        private readonly TransferEligibilityService $eligibility,
        private readonly TransferInventorySynchronizer $inventory,
        private readonly TransferTimelineRecorder $timeline,
    ) {}

    public function decide(
        BranchTransfer $transfer,
        ApprovalSide $side,
        ApprovalDecision $decision,
        ?string $notes,
        User $actor,
    ): BranchTransfer {
        return DB::transaction(function () use ($transfer, $side, $decision, $notes, $actor): BranchTransfer {
            $locked = BranchTransfer::query()
                ->with(['items.asset', 'items.product', 'originBranch', 'destinationBranch'])
                ->lockForUpdate()
                ->findOrFail($transfer->id);

            if ($locked->status !== TransferStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'transfer' => 'Transfer tidak sedang menunggu persetujuan.',
                ]);
            }

            $branchId = $side === ApprovalSide::Origin
                ? $locked->from_branch_id
                : $locked->to_branch_id;
            $this->guardSide($branchId, $actor);

            $existing = BranchTransferApproval::query()
                ->where('branch_transfer_id', $locked->id)
                ->where('revision_number', $locked->revision_number)
                ->where('side', $side->value)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->decision === $decision && $existing->decided_by === $actor->id) {
                    return $locked;
                }

                throw ValidationException::withMessages([
                    'decision' => 'Keputusan untuk sisi ini sudah dicatat pada revisi aktif.',
                ]);
            }

            $sameActorOtherSide = BranchTransferApproval::query()
                ->where('branch_transfer_id', $locked->id)
                ->where('revision_number', $locked->revision_number)
                ->where('decision', ApprovalDecision::Approved->value)
                ->where('decided_by', $actor->id)
                ->exists();
            if ($sameActorOtherSide) {
                throw ValidationException::withMessages([
                    'decision' => 'Akun yang sama tidak boleh menyetujui kedua sisi transfer.',
                ]);
            }

            if ($decision === ApprovalDecision::Approved) {
                $this->eligibility->assertEligible($locked);
            }

            $snapshot = $this->snapshots->makeWithHash($locked);
            BranchTransferApproval::query()->create([
                'branch_transfer_id' => $locked->id,
                'revision_number' => $locked->revision_number,
                'side' => $side->value,
                'branch_id' => $branchId,
                'decision' => $decision->value,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'notes' => $notes,
                'payload_snapshot' => $snapshot['snapshot'],
                'snapshot_hash' => $snapshot['hash'],
            ]);

            if ($decision === ApprovalDecision::Rejected) {
                $locked->forceFill([
                    'status' => TransferStatus::Rejected->value,
                    'lock_version' => $locked->lock_version + 1,
                ])->save();
                $this->timeline->transferStatus(
                    $locked,
                    TransferStatus::PendingApproval->value,
                    TransferStatus::Rejected->value,
                    $notes ?? 'Transfer ditolak.',
                    $actor,
                    $branchId,
                );

                return $locked;
            }

            $approvedSides = BranchTransferApproval::query()
                ->where('branch_transfer_id', $locked->id)
                ->where('revision_number', $locked->revision_number)
                ->where('decision', ApprovalDecision::Approved->value)
                ->distinct()
                ->count('side');

            if ($approvedSides < 2) {
                return $locked;
            }

            $this->eligibility->assertEligible($locked);
            $before = $locked->items->mapWithKeys(function (BranchTransferItem $item): array {
                if ($item->asset === null) {
                    return [];
                }

                return [$item->id => [
                    'status' => $item->asset->status,
                    'condition' => $item->asset->condition,
                ]];
            });
            $this->inventory->hold($locked);
            $locked->forceFill([
                'status' => TransferStatus::Approved->value,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            $locked->load('items.asset');
            foreach ($locked->items as $item) {
                if ($item->asset === null) {
                    continue;
                }
                /** @var array{status: string, condition: string}|null $previous */
                $previous = $before->get($item->id);
                if ($previous === null) {
                    throw ValidationException::withMessages([
                        'transfer' => 'Snapshot status aset tidak lengkap. Muat ulang dan ulangi persetujuan.',
                    ]);
                }

                $this->timeline->asset(
                    $item->asset,
                    $item,
                    $previous['status'],
                    'in_transit',
                    $previous['condition'],
                    $item->asset->condition,
                    "Transfer {$locked->transfer_number} disetujui dua pihak.",
                    $actor,
                    $locked->from_branch_id,
                );
            }
            $this->timeline->transferStatus(
                $locked,
                TransferStatus::PendingApproval->value,
                TransferStatus::Approved->value,
                'Persetujuan cabang asal dan tujuan lengkap.',
                $actor,
                $branchId,
            );

            return $locked;
        }, 3);
    }

    private function guardSide(int $branchId, User $actor): void
    {
        if ($actor->current_branch_id === $branchId) {
            return;
        }

        if ($actor->can('transfers.override') && $actor->accessibleBranches()->whereKey($branchId)->exists()) {
            return;
        }

        abort(403, 'Aktifkan cabang yang akan Anda wakili sebelum memberikan keputusan.');
    }
}
