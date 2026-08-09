<?php

namespace App\Domain\Transfers;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchTransferManager
{
    /** @var list<string> */
    private const MATERIAL_FIELDS = [
        'from_branch_id',
        'to_branch_id',
        'reason',
        'planned_dispatch_at',
        'expected_arrival_at',
    ];

    public function __construct(
        private readonly TransferNumberGenerator $numbers,
        private readonly TransferInventorySynchronizer $inventory,
        private readonly TransferTimelineRecorder $timeline,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(array $data, User $actor): BranchTransfer
    {
        return DB::transaction(function () use ($data, $actor): BranchTransfer {
            [$origin, $destination] = $this->branches($data, $actor);
            $this->guardActorInvolvement($origin, $destination, $actor);

            $transfer = BranchTransfer::query()->create([
                ...Arr::only($data, [
                    'from_branch_id',
                    'to_branch_id',
                    'reason',
                    'planned_dispatch_at',
                    'expected_arrival_at',
                    'shipping_notes',
                    'shipping_method',
                    'courier_name',
                    'courier_phone',
                    'vehicle_number',
                    'tracking_number',
                    'waybill_number',
                    'seal_number',
                ]),
                'company_id' => $actor->company_id,
                'transfer_number' => $this->numbers->next($origin),
                'status' => TransferStatus::Draft->value,
                'revision_number' => 1,
                'lock_version' => 0,
                'requested_by' => $actor->id,
            ]);
            $this->replaceItems($transfer, $data['items'] ?? [], $actor);
            $this->timeline->transferStatus(
                $transfer,
                null,
                TransferStatus::Draft->value,
                'Draft transfer dibuat.',
                $actor,
                $actor->current_branch_id,
            );

            return $transfer->fresh(['items.asset', 'items.product', 'originBranch', 'destinationBranch']);
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(BranchTransfer $transfer, array $data, User $actor): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $data, $actor): BranchTransfer {
            $locked = BranchTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $locked->load('items');
            $this->guardEditable($locked);
            $this->guardLockVersion($locked, (int) $data['lock_version']);

            [$origin, $destination] = $this->branches($data, $actor);
            $this->guardActorInvolvement($origin, $destination, $actor);
            $material = $this->isMaterialChange($locked, $data);
            $previousStatus = $locked->status->value;

            if ($material && $locked->status->holdsInventory()) {
                $this->inventory->release($locked);
            }

            $locked->forceFill([
                ...Arr::only($data, [
                    'from_branch_id',
                    'to_branch_id',
                    'reason',
                    'planned_dispatch_at',
                    'expected_arrival_at',
                    'shipping_notes',
                    'shipping_method',
                    'courier_name',
                    'courier_phone',
                    'vehicle_number',
                    'tracking_number',
                    'waybill_number',
                    'seal_number',
                ]),
                'lock_version' => $locked->lock_version + 1,
            ]);

            if ($locked->status === TransferStatus::Rejected) {
                $locked->forceFill([
                    'revision_number' => $locked->revision_number + 1,
                    'status' => TransferStatus::Draft->value,
                    'approved_by' => null,
                    'approved_at' => null,
                    'last_material_changed_by' => $actor->id,
                    'last_material_changed_at' => now(),
                ]);
            } elseif ($material && $locked->status !== TransferStatus::Draft) {
                $locked->forceFill([
                    'revision_number' => $locked->revision_number + 1,
                    'status' => TransferStatus::PendingApproval->value,
                    'approved_by' => null,
                    'approved_at' => null,
                    'last_material_changed_by' => $actor->id,
                    'last_material_changed_at' => now(),
                ]);
            }

            $locked->save();
            if ($material) {
                $this->replaceItems($locked, $data['items'] ?? [], $actor);
            }

            if ($previousStatus !== $locked->status->value) {
                $this->timeline->transferStatus(
                    $locked,
                    $previousStatus,
                    $locked->status->value,
                    'Perubahan material membutuhkan persetujuan ulang.',
                    $actor,
                );
            }

            return $locked->fresh(['items.asset', 'items.product', 'originBranch', 'destinationBranch']);
        }, 3);
    }

    public function submit(BranchTransfer $transfer, User $actor): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $actor): BranchTransfer {
            $locked = BranchTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $this->guardActorInvolvement($locked->originBranch, $locked->destinationBranch, $actor);

            if (! in_array($locked->status, [TransferStatus::Draft, TransferStatus::PendingApproval], true)) {
                throw ValidationException::withMessages([
                    'transfer' => 'Transfer ini tidak dapat diajukan pada status saat ini.',
                ]);
            }

            if ($locked->items()->count() === 0) {
                throw ValidationException::withMessages([
                    'items' => 'Transfer minimal memiliki satu item.',
                ]);
            }

            if ($locked->status === TransferStatus::Draft) {
                $locked->forceFill([
                    'status' => TransferStatus::PendingApproval->value,
                    'requested_by' => $actor->id,
                    'requested_at' => now(),
                    'lock_version' => $locked->lock_version + 1,
                ])->save();
                $this->timeline->transferStatus(
                    $locked,
                    TransferStatus::Draft->value,
                    TransferStatus::PendingApproval->value,
                    'Transfer diajukan untuk persetujuan dua pihak.',
                    $actor,
                );
            }

            return $locked->fresh(['items.asset', 'items.product', 'originBranch', 'destinationBranch']);
        }, 3);
    }

    public function cancel(BranchTransfer $transfer, string $reason, User $actor): BranchTransfer
    {
        return DB::transaction(function () use ($transfer, $reason, $actor): BranchTransfer {
            $locked = BranchTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $locked->load('items');
            $this->guardActorInvolvement($locked->originBranch, $locked->destinationBranch, $actor);

            if (! in_array($locked->status, [
                TransferStatus::Draft,
                TransferStatus::PendingApproval,
                TransferStatus::Approved,
                TransferStatus::Rejected,
            ], true)) {
                throw ValidationException::withMessages([
                    'transfer' => 'Transfer yang sudah dikirim tidak dapat dibatalkan.',
                ]);
            }

            $previous = $locked->status->value;
            if ($locked->status->holdsInventory()) {
                $this->inventory->release($locked);
            }

            $locked->forceFill([
                'status' => TransferStatus::Cancelled->value,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'lock_version' => $locked->lock_version + 1,
            ])->save();
            $locked->items()->update(['status' => 'cancelled']);
            $this->timeline->transferStatus(
                $locked,
                $previous,
                TransferStatus::Cancelled->value,
                $reason,
                $actor,
            );

            return $locked;
        }, 3);
    }

    private function guardEditable(BranchTransfer $transfer): void
    {
        if (! in_array($transfer->status, [
            TransferStatus::Draft,
            TransferStatus::PendingApproval,
            TransferStatus::Approved,
            TransferStatus::Rejected,
        ], true)) {
            throw ValidationException::withMessages([
                'transfer' => 'Transfer tidak dapat diedit setelah pengiriman dimulai.',
            ]);
        }
    }

    private function guardLockVersion(BranchTransfer $transfer, int $lockVersion): void
    {
        if ($transfer->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lock_version' => 'Dokumen telah berubah. Muat ulang halaman sebelum menyimpan kembali.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Branch, 1: Branch}
     */
    private function branches(array $data, User $actor): array
    {
        $origin = Branch::query()
            ->where('company_id', $actor->company_id)
            ->where('is_active', true)
            ->whereKey((int) $data['from_branch_id'])
            ->firstOrFail();
        $destination = Branch::query()
            ->where('company_id', $actor->company_id)
            ->where('is_active', true)
            ->whereKey((int) $data['to_branch_id'])
            ->firstOrFail();

        if ($origin->is($destination)) {
            throw ValidationException::withMessages([
                'to_branch_id' => 'Cabang tujuan harus berbeda dari cabang asal.',
            ]);
        }

        return [$origin, $destination];
    }

    private function guardActorInvolvement(Branch $origin, Branch $destination, User $actor): void
    {
        $currentBranchId = $actor->current_branch_id;
        $isInvolved = $currentBranchId === $origin->id || $currentBranchId === $destination->id;

        if (! $isInvolved && ! $actor->can('transfers.override')) {
            abort(403, 'Aktifkan salah satu cabang yang terlibat untuk memproses transfer.');
        }
    }

    /** @param list<array<string, mixed>> $items */
    private function replaceItems(BranchTransfer $transfer, array $items, User $actor): void
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Transfer minimal memiliki satu item.',
            ]);
        }

        $assetIds = collect($items)->pluck('asset_id')->filter()->map(fn ($id): int => (int) $id);
        if ($assetIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Aset yang sama tidak boleh ditambahkan dua kali.',
            ]);
        }

        $transfer->items()->delete();

        foreach ($items as $index => $item) {
            $product = Product::query()
                ->where('company_id', $actor->company_id)
                ->where('is_active', true)
                ->whereKey((int) $item['product_id'])
                ->firstOrFail();
            $assetId = isset($item['asset_id']) && $item['asset_id'] !== ''
                ? (int) $item['asset_id']
                : null;
            $quantity = (int) ($item['quantity'] ?? 1);

            if ($product->tracking_type === 'serialized' && $assetId === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.asset_id" => 'Produk serialized wajib memilih unit aset.',
                ]);
            }

            if ($assetId !== null) {
                $asset = Asset::query()
                    ->where('product_id', $product->id)
                    ->where('current_branch_id', $transfer->from_branch_id)
                    ->findOrFail($assetId);
                $quantity = 1;
            } elseif ($quantity < 1) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => 'Jumlah transfer minimal satu unit.',
                ]);
            }

            BranchTransferItem::query()->create([
                'branch_transfer_id' => $transfer->id,
                'line_number' => $index + 1,
                'product_id' => $product->id,
                'asset_id' => $assetId,
                'quantity' => $quantity,
                'condition_before' => $item['condition_before'] ?? null,
                'status' => 'pending',
                'notes' => $item['notes'] ?? null,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function isMaterialChange(BranchTransfer $transfer, array $data): bool
    {
        foreach (self::MATERIAL_FIELDS as $field) {
            $current = $transfer->getAttribute($field);
            $incoming = $data[$field] ?? null;
            if (in_array($field, ['planned_dispatch_at', 'expected_arrival_at'], true)) {
                $currentValue = $current instanceof \DateTimeInterface
                    ? CarbonImmutable::instance($current)->seconds(0)->toDateTimeString()
                    : '';
                $incomingValue = $incoming === null || $incoming === ''
                    ? ''
                    : CarbonImmutable::parse((string) $incoming)->seconds(0)->toDateTimeString();
            } else {
                $currentValue = $current === null ? '' : (string) $current;
                $incomingValue = $incoming === null ? '' : (string) $incoming;
            }

            if ($currentValue !== $incomingValue) {
                return true;
            }
        }

        $currentItems = $transfer->items
            ->map(fn (BranchTransferItem $item): array => [
                'product_id' => $item->product_id,
                'asset_id' => $item->asset_id,
                'quantity' => $item->quantity,
                'condition_before' => $item->condition_before,
                'notes' => $item->notes,
            ])->values()->all();
        $incomingItems = [];
        $rawItems = $data['items'] ?? [];
        if (is_array($rawItems)) {
            foreach ($rawItems as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $rawAssetId = $item['asset_id'] ?? null;
                $incomingItems[] = [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'asset_id' => $rawAssetId === null || $rawAssetId === ''
                        ? null
                        : (int) $rawAssetId,
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'condition_before' => $item['condition_before'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ];
            }
        }

        return $currentItems !== $incomingItems;
    }
}
