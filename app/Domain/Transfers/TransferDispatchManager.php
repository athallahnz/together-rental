<?php

namespace App\Domain\Transfers;

use App\Domain\Transfers\Enums\CaptureMode;
use App\Domain\Transfers\Enums\TransferStatus;
use App\Models\AssetInspection;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class TransferDispatchManager
{
    public function __construct(
        private readonly TransferEligibilityService $eligibility,
        private readonly TransferSettings $settings,
        private readonly TransferMediaManager $media,
        private readonly TransferTimelineRecorder $timeline,
    ) {}

    /** @param array<string, mixed> $data */
    public function dispatch(BranchTransfer $transfer, array $data, User $actor): BranchTransfer
    {
        return $this->media->transactional(function () use ($transfer, $data, $actor): BranchTransfer {
            $locked = BranchTransfer::query()
                ->with(['items.asset', 'items.product', 'originBranch', 'destinationBranch'])
                ->lockForUpdate()
                ->findOrFail($transfer->id);

            if ($locked->status === TransferStatus::Dispatched) {
                return $locked;
            }

            if ($locked->status !== TransferStatus::Approved) {
                throw ValidationException::withMessages([
                    'transfer' => 'Hanya transfer yang telah disetujui dua pihak yang dapat dikirim.',
                ]);
            }

            $this->guardOrigin($locked, $actor);
            $policy = $this->settings->dispatch($locked->from_branch_id);
            if ($policy['require_waybill'] && trim((string) ($data['waybill_number'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'waybill_number' => 'Nomor surat jalan wajib diisi.',
                ]);
            }

            $this->eligibility->assertEligible($locked, true);

            /** @var array<int, array<string, mixed>> $inspections */
            $inspections = [];
            $rawInspections = $data['inspections'] ?? [];
            if (is_array($rawInspections)) {
                foreach ($rawInspections as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $itemId = (int) ($row['item_id'] ?? 0);
                    if ($itemId > 0) {
                        $inspections[$itemId] = $row;
                    }
                }
            }

            /** @var list<int> $knownItemIds */
            $knownItemIds = array_map(
                static fn ($id): int => (int) $id,
                $locked->items->modelKeys(),
            );
            if (array_diff(array_keys($inspections), $knownItemIds) !== []) {
                throw ValidationException::withMessages([
                    'inspections' => 'Payload pemeriksaan memuat item dari transfer lain.',
                ]);
            }

            foreach ($locked->items as $item) {
                $payload = $inspections[(int) $item->id] ?? null;
                if (! is_array($payload)) {
                    throw ValidationException::withMessages([
                        'inspections' => "Pemeriksaan keberangkatan item {$item->line_number} belum lengkap.",
                    ]);
                }

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
                    $inspection = AssetInspection::query()->firstOrCreate(
                        [
                            'branch_transfer_item_id' => $item->id,
                            'type' => 'transfer_dispatch',
                        ],
                        [
                            'branch_id' => $locked->from_branch_id,
                            'asset_id' => $item->asset_id,
                            'condition' => $payload['condition'],
                            'checklist' => $payload['checklist'] ?? null,
                            'notes' => $payload['notes'] ?? null,
                            'inspected_by' => $actor->id,
                            'inspected_at' => now(),
                        ],
                    );
                    $this->media->attachInspectionPhotos(
                        $locked,
                        $inspection,
                        $photos,
                        $captureSource,
                        $actor,
                    );
                } else {
                    foreach ($photos as $photo) {
                        $this->media->storeDocument(
                            $locked,
                            $photo,
                            'dispatch',
                            'item_evidence',
                            $captureSource,
                            $actor,
                            $item,
                        );
                    }
                }

                $item->forceFill([
                    'condition_before' => $payload['condition'],
                    'status' => 'dispatched',
                ])->save();
            }

            if (($data['waybill_file'] ?? null) instanceof UploadedFile) {
                $this->media->storeDocument(
                    $locked,
                    $data['waybill_file'],
                    'dispatch',
                    'waybill',
                    'gallery',
                    $actor,
                );
            }

            $locked->forceFill([
                ...Arr::only($data, [
                    'shipping_method',
                    'courier_name',
                    'courier_phone',
                    'vehicle_number',
                    'tracking_number',
                    'waybill_number',
                    'seal_number',
                    'shipping_notes',
                ]),
                'status' => TransferStatus::Dispatched->value,
                'shipped_by' => $actor->id,
                'shipped_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $this->timeline->transferStatus(
                $locked,
                TransferStatus::Approved->value,
                TransferStatus::Dispatched->value,
                'Aset diserahkan kepada kurir/pengantar.',
                $actor,
                $locked->from_branch_id,
            );

            return $locked->fresh(['items.asset', 'items.inspections.media']);
        });
    }

    private function guardOrigin(BranchTransfer $transfer, User $actor): void
    {
        if ($actor->current_branch_id === $transfer->from_branch_id) {
            return;
        }

        if ($actor->can('transfers.override') && $actor->accessibleBranches()->whereKey($transfer->from_branch_id)->exists()) {
            return;
        }

        abort(403, 'Dispatch hanya dapat dilakukan dari cabang asal.');
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
                'inspections' => "Minimal {$policy['min_photos']} foto kondisi wajib disertakan per item.",
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
                'inspections' => 'Kebijakan cabang mewajibkan foto diambil langsung melalui kamera.',
            ]);
        }
    }
}
