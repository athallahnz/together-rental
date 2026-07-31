<?php

namespace App\Domain\Transfers;

use App\Domain\Maintenance\MaintenanceManager;
use App\Domain\Transfers\Enums\CaptureMode;
use App\Domain\Transfers\Enums\DiscrepancyResolution;
use App\Domain\Transfers\Enums\ReceivingResult;
use App\Domain\Transfers\Enums\TransferItemStatus;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferReceivingManager
{
    public function __construct(
        private readonly TransferInventorySynchronizer $inventory,
        private readonly TransferSettings $settings,
        private readonly TransferMediaManager $media,
        private readonly TransferTimelineRecorder $timeline,
        private readonly MaintenanceManager $maintenance,
    ) {}

    /** @param array<string, mixed> $data */
    public function receive(BranchTransfer $transfer, array $data, User $actor): BranchTransfer
    {
        return $this->media->transactional(function () use ($transfer, $data, $actor): BranchTransfer {
            $locked = BranchTransfer::query()
                ->with(['items.asset', 'items.product'])
                ->lockForUpdate()
                ->findOrFail($transfer->id);
            $this->guardDestination($locked, $actor);

            /** @var array<int, array<string, mixed>> $payloads */
            $payloads = [];
            $rawItems = $data['items'] ?? [];
            if (is_array($rawItems)) {
                foreach ($rawItems as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $itemId = (int) ($row['item_id'] ?? 0);
                    if ($itemId > 0) {
                        $payloads[$itemId] = $row;
                    }
                }
            }

            /** @var list<int> $knownItemIds */
            $knownItemIds = array_map(
                static fn ($id): int => (int) $id,
                $locked->items->modelKeys(),
            );
            $foreignItemIds = array_diff(array_keys($payloads), $knownItemIds);
            if ($foreignItemIds !== []) {
                throw ValidationException::withMessages([
                    'items' => 'Payload penerimaan memuat item dari transfer lain.',
                ]);
            }

            if ($locked->status === TransferStatus::Completed) {
                return $locked;
            }

            if (! in_array($locked->status, [
                TransferStatus::Dispatched,
                TransferStatus::Receiving,
                TransferStatus::Discrepancy,
            ], true)) {
                throw ValidationException::withMessages([
                    'transfer' => 'Transfer belum dapat diproses penerimaannya.',
                ]);
            }

            $policy = $this->settings->receiving($locked->to_branch_id);
            $previousStatus = $locked->status->value;
            $processedItems = 0;

            foreach ($locked->items as $item) {
                $payload = $payloads[(int) $item->id] ?? null;
                if (! is_array($payload)) {
                    continue;
                }

                $item = BranchTransferItem::query()->lockForUpdate()->findOrFail($item->id);
                if (in_array($item->status, [
                    TransferItemStatus::Received,
                    TransferItemStatus::Resolved,
                    TransferItemStatus::Discrepancy,
                ], true)) {
                    continue;
                }

                $processedItems++;
                $result = ReceivingResult::from((string) $payload['receiving_result']);
                /** @var list<UploadedFile> $photos */
                $photos = array_values(array_filter(
                    is_array($payload['photos'] ?? null) ? $payload['photos'] : [],
                    static fn ($photo): bool => $photo instanceof UploadedFile,
                ));
                $captureSource = (string) ($payload['capture_source'] ?? 'gallery');
                $this->guardCapturePolicy(
                    $captureSource,
                    count($photos),
                    $policy,
                    (string) ($data['override_reason'] ?? ''),
                    $actor,
                );

                if ($item->asset_id !== null) {
                    $this->receiveSerialized($locked, $item, $payload, $result, $photos, $captureSource, $actor);
                } else {
                    $this->receivePooled($locked, $item, $payload, $result, $photos, $captureSource, $actor);
                }
            }

            if ($processedItems === 0) {
                $requestedItems = $locked->items->whereIn('id', array_keys($payloads));
                $alreadyProcessed = $requestedItems->count() === count($payloads)
                    && $requestedItems->every(fn (BranchTransferItem $item): bool => in_array(
                        $item->status,
                        [
                            TransferItemStatus::Received,
                            TransferItemStatus::Resolved,
                            TransferItemStatus::Discrepancy,
                        ],
                        true,
                    ));

                if ($alreadyProcessed) {
                    return $locked;
                }

                throw ValidationException::withMessages([
                    'items' => 'Tidak ada item aktif yang dapat diproses pada penerimaan ini.',
                ]);
            }

            $locked->forceFill([
                'receiving_notes' => $data['receiving_notes'] ?? $locked->receiving_notes,
                'received_by' => $actor->id,
                'received_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->refreshStatus($locked);
            $locked->save();

            if ($previousStatus !== $locked->status->value) {
                $this->timeline->transferStatus(
                    $locked,
                    $previousStatus,
                    $locked->status->value,
                    'Penerimaan transfer diproses oleh cabang tujuan.',
                    $actor,
                    $locked->to_branch_id,
                );
            }

            return $locked->fresh([
                'items.asset',
                'items.inspections.media',
                'originBranch',
                'destinationBranch',
            ]);
        });
    }

    public function resolve(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        DiscrepancyResolution $resolution,
        string $notes,
        User $actor,
    ): BranchTransfer {
        return DB::transaction(function () use ($transfer, $item, $resolution, $notes, $actor): BranchTransfer {
            $locked = BranchTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $lockedItem = BranchTransferItem::query()->lockForUpdate()->findOrFail($item->id);
            $this->guardDestination($locked, $actor);

            if ($lockedItem->branch_transfer_id !== $locked->id) {
                throw ValidationException::withMessages([
                    'item' => 'Item tidak berada pada transfer ini.',
                ]);
            }

            if ($lockedItem->status === TransferItemStatus::Resolved) {
                if ($lockedItem->resolution_action === $resolution->value) {
                    return $locked;
                }

                throw ValidationException::withMessages([
                    'item' => 'Discrepancy sudah diselesaikan dengan tindakan berbeda.',
                ]);
            }

            if ($lockedItem->status !== TransferItemStatus::Discrepancy) {
                throw ValidationException::withMessages([
                    'item' => 'Item tidak berada pada discrepancy aktif.',
                ]);
            }

            if ($lockedItem->asset_id !== null) {
                $this->resolveSerialized($locked, $lockedItem, $resolution, $notes, $actor);
            } else {
                $this->resolvePooled($locked, $lockedItem, $resolution);
            }

            $resolvedReceivedQuantity = $resolution === DiscrepancyResolution::AcceptAtDestination
                ? $lockedItem->quantity
                : $lockedItem->received_quantity;

            $lockedItem->forceFill([
                'status' => 'resolved',
                'resolution_action' => $resolution->value,
                'discrepancy_notes' => trim($lockedItem->discrepancy_notes.' '.$notes),
                'received_quantity' => $resolvedReceivedQuantity,
                'resolved_by' => $actor->id,
                'resolved_at' => now(),
            ])->save();

            $previousStatus = $locked->status->value;
            $this->refreshStatus($locked);
            $locked->forceFill(['lock_version' => $locked->lock_version + 1])->save();

            if ($previousStatus !== $locked->status->value) {
                $this->timeline->transferStatus(
                    $locked,
                    $previousStatus,
                    $locked->status->value,
                    'Seluruh discrepancy transfer telah diselesaikan.',
                    $actor,
                    $locked->to_branch_id,
                );
            }

            return $locked->fresh(['items.asset']);
        }, 3);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $photos
     */
    private function receiveSerialized(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        array $payload,
        ReceivingResult $result,
        array $photos,
        string $captureSource,
        User $actor,
    ): void {
        $asset = Asset::query()->lockForUpdate()->findOrFail($item->asset_id);
        if ($asset->status !== 'in_transit' || $asset->current_branch_id !== $transfer->from_branch_id) {
            throw ValidationException::withMessages([
                'items' => "Aset {$asset->asset_code} tidak lagi konsisten dengan transfer.",
            ]);
        }

        $condition = (string) ($payload['condition'] ?? $asset->condition);
        $inspection = AssetInspection::query()->firstOrCreate(
            [
                'branch_transfer_item_id' => $item->id,
                'type' => 'transfer_receiving',
            ],
            [
                'branch_id' => $transfer->to_branch_id,
                'asset_id' => $asset->id,
                'condition' => $condition,
                'checklist' => $payload['checklist'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'inspected_by' => $actor->id,
                'inspected_at' => now(),
            ],
        );
        $this->media->attachInspectionPhotos($transfer, $inspection, $photos, $captureSource, $actor);

        if ($result->createsDiscrepancy()) {
            $item->forceFill([
                'receiving_result' => $result->value,
                'condition_after' => $condition,
                'discrepancy_type' => $result->value,
                'discrepancy_notes' => $payload['notes'] ?? null,
                'status' => 'discrepancy',
            ])->save();

            return;
        }

        $previousStatus = $asset->status;
        $previousCondition = $asset->condition;
        $asset = $this->inventory->receiveSerialized(
            $transfer,
            $item,
            'available',
            $condition,
        );
        $this->timeline->asset(
            $asset,
            $item,
            $previousStatus,
            'available',
            $previousCondition,
            $condition,
            "Aset diterima melalui {$transfer->transfer_number}.",
            $actor,
            $transfer->to_branch_id,
        );

        $item->forceFill([
            'received_quantity' => 1,
            'receiving_result' => $result->value,
            'condition_after' => $condition,
            'status' => 'received',
        ])->save();

        if ($result->createsMaintenance()) {
            $this->maintenance->create([
                'asset_id' => $asset->id,
                'type' => 'transfer_receiving',
                'problem_description' => $payload['notes'] ?? 'Temuan kondisi pada penerimaan transfer.',
                'vendor_name' => null,
                'estimated_cost' => 0,
            ], $actor);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<UploadedFile> $photos
     */
    private function receivePooled(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        array $payload,
        ReceivingResult $result,
        array $photos,
        string $captureSource,
        User $actor,
    ): void {
        foreach ($photos as $photo) {
            $this->media->storeDocument(
                $transfer,
                $photo,
                'receiving',
                'item_evidence',
                $captureSource,
                $actor,
                $item,
            );
        }

        if ($result !== ReceivingResult::AcceptedGood) {
            $item->forceFill([
                'receiving_result' => $result->value,
                'discrepancy_type' => $result->value,
                'discrepancy_notes' => $payload['notes'] ?? null,
                'status' => 'discrepancy',
            ])->save();

            return;
        }

        $quantity = (int) ($payload['quantity'] ?? ($item->quantity - $item->received_quantity));
        $this->inventory->receivePooled($transfer, $item, $quantity);
        $received = $item->received_quantity + $quantity;
        $item->forceFill([
            'received_quantity' => $received,
            'receiving_result' => $result->value,
            'condition_after' => $payload['condition'] ?? 'good',
            'status' => $received >= $item->quantity ? 'received' : 'dispatched',
        ])->save();
    }

    private function resolveSerialized(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        DiscrepancyResolution $resolution,
        string $notes,
        User $actor,
    ): void {
        $asset = Asset::query()->lockForUpdate()->findOrFail($item->asset_id);
        $fromStatus = $asset->status;
        $fromCondition = $asset->condition;

        if ($resolution === DiscrepancyResolution::AcceptAtDestination) {
            $asset = $this->inventory->receiveSerialized(
                $transfer,
                $item,
                'available',
                $item->condition_after ?? $asset->condition,
            );
        } elseif ($resolution === DiscrepancyResolution::ReturnToOrigin) {
            $asset->forceFill([
                'current_branch_id' => $transfer->from_branch_id,
                'status' => $item->previous_asset_status ?? 'available',
            ])->save();
            $this->inventory->syncSerialized($item->product_id, $transfer->from_branch_id);
        } else {
            $asset->forceFill(['status' => 'lost'])->save();
            $this->inventory->syncSerialized($item->product_id, $transfer->from_branch_id);
        }

        $this->timeline->asset(
            $asset,
            $item,
            $fromStatus,
            $asset->status,
            $fromCondition,
            $asset->condition,
            $notes,
            $actor,
            $asset->current_branch_id,
        );
    }

    private function resolvePooled(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        DiscrepancyResolution $resolution,
    ): void {
        if ($resolution === DiscrepancyResolution::AcceptAtDestination) {
            $this->inventory->receivePooled(
                $transfer,
                $item,
                max(1, $item->quantity - $item->received_quantity),
            );
        } elseif ($resolution === DiscrepancyResolution::ReturnToOrigin) {
            $this->inventory->resolvePooledToOrigin($transfer, $item);
        } else {
            $this->inventory->markPooledLost($transfer, $item);
        }
    }

    private function refreshStatus(BranchTransfer $transfer): void
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, BranchTransferItem> $items */
        $items = BranchTransferItem::query()
            ->where('branch_transfer_id', $transfer->id)
            ->get(['id', 'status']);

        if ($items->contains(
            static fn (BranchTransferItem $item): bool => $item->status === TransferItemStatus::Discrepancy,
        )) {
            $transfer->status = TransferStatus::Discrepancy;
            $transfer->completed_at = null;

            return;
        }

        if ($items->isNotEmpty() && $items->every(
            static fn (BranchTransferItem $item): bool => in_array(
                $item->status,
                [TransferItemStatus::Received, TransferItemStatus::Resolved],
                true,
            ),
        )) {
            $transfer->status = TransferStatus::Completed;
            $transfer->completed_at = Carbon::now();

            return;
        }

        $transfer->status = TransferStatus::Receiving;
        $transfer->completed_at = null;
    }

    private function guardDestination(BranchTransfer $transfer, User $actor): void
    {
        if ($actor->current_branch_id === $transfer->to_branch_id) {
            return;
        }

        if ($actor->can('transfers.override') && $actor->accessibleBranches()->whereKey($transfer->to_branch_id)->exists()) {
            return;
        }

        abort(403, 'Penerimaan hanya dapat dilakukan oleh cabang tujuan.');
    }

    /**
     * @param array{capture_mode: string, min_photos: int, require_waybill: bool, allow_gallery_override: bool} $policy
     */
    private function guardCapturePolicy(
        string $captureSource,
        int $photoCount,
        array $policy,
        string $overrideReason,
        User $actor,
    ): void {
        if ($photoCount < $policy['min_photos']) {
            throw ValidationException::withMessages([
                'items' => "Minimal {$policy['min_photos']} foto penerimaan wajib disertakan per item.",
            ]);
        }

        if ($policy['capture_mode'] !== CaptureMode::CameraRequired->value || $captureSource === 'camera') {
            return;
        }

        $overrideAllowed = $policy['allow_gallery_override']
            && $actor->can('transfers.override')
            && mb_strlen(trim($overrideReason)) >= 5;

        if (! $overrideAllowed) {
            throw ValidationException::withMessages([
                'items' => 'Kebijakan cabang mewajibkan bukti penerimaan dari kamera realtime.',
            ]);
        }
    }
}
