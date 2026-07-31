<?php

namespace App\Domain\Transfers;

use App\Models\Asset;
use App\Models\BranchInventory;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use Illuminate\Validation\ValidationException;

class TransferInventorySynchronizer
{
    public function hold(BranchTransfer $transfer): void
    {
        foreach ($transfer->items as $item) {
            if ($item->asset_id !== null) {
                $asset = Asset::query()->lockForUpdate()->findOrFail($item->asset_id);
                $item->forceFill([
                    'previous_branch_id' => $asset->current_branch_id,
                    'previous_asset_status' => $asset->status,
                    'status' => 'held',
                ])->save();
                $asset->forceFill(['status' => 'in_transit'])->save();
                $this->syncSerialized($item->product_id, $transfer->from_branch_id);

                continue;
            }

            $inventory = $this->lockInventory($transfer->from_branch_id, $item->product_id);
            $available = $this->availableQuantity($inventory);
            if ($available < $item->quantity) {
                throw ValidationException::withMessages([
                    'transfer' => "Stok produk {$item->product_id} tidak lagi mencukupi untuk dikunci.",
                ]);
            }
            $inventory->increment('quantity_in_transfer', $item->quantity);
            $item->forceFill(['status' => 'held'])->save();
        }
    }

    public function release(BranchTransfer $transfer): void
    {
        $transfer->loadMissing('items');

        foreach ($transfer->items as $item) {
            if ($item->asset_id !== null) {
                $asset = Asset::query()->lockForUpdate()->find($item->asset_id);
                if ($asset !== null && $asset->status === 'in_transit') {
                    $asset->forceFill([
                        'current_branch_id' => $item->previous_branch_id ?? $transfer->from_branch_id,
                        'status' => $item->previous_asset_status ?? 'available',
                    ])->save();
                    $this->syncSerialized($item->product_id, $transfer->from_branch_id);
                }
            } else {
                $inventory = $this->lockInventory($transfer->from_branch_id, $item->product_id);
                $inventory->forceFill([
                    'quantity_in_transfer' => max(
                        0,
                        $inventory->quantity_in_transfer - max(0, $item->quantity - $item->received_quantity),
                    ),
                ])->save();
            }

            $item->forceFill([
                'status' => 'pending',
                'previous_branch_id' => null,
                'previous_asset_status' => null,
            ])->save();
        }
    }

    public function receiveSerialized(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        string $status,
        string $condition,
    ): Asset {
        $asset = Asset::query()->lockForUpdate()->findOrFail($item->asset_id);
        $asset->forceFill([
            'current_branch_id' => $transfer->to_branch_id,
            'status' => $status,
            'condition' => $condition,
        ])->save();

        $this->syncSerialized($item->product_id, $transfer->from_branch_id);
        $this->syncSerialized($item->product_id, $transfer->to_branch_id);

        return $asset;
    }

    public function receivePooled(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        int $quantity,
    ): void {
        $origin = $this->lockInventory($transfer->from_branch_id, $item->product_id);
        $destination = $this->lockInventory($transfer->to_branch_id, $item->product_id, true);

        if ($quantity < 1 || $quantity > ($item->quantity - $item->received_quantity)) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah penerimaan tidak valid.',
            ]);
        }

        if ($origin->quantity_on_hand < $quantity || $origin->quantity_in_transfer < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'Stok transfer cabang asal tidak konsisten.',
            ]);
        }

        $origin->forceFill([
            'quantity_on_hand' => $origin->quantity_on_hand - $quantity,
            'quantity_in_transfer' => $origin->quantity_in_transfer - $quantity,
        ])->save();
        $destination->increment('quantity_on_hand', $quantity);
    }

    public function resolvePooledToOrigin(BranchTransfer $transfer, BranchTransferItem $item): void
    {
        $remaining = max(0, $item->quantity - $item->received_quantity);
        if ($remaining === 0) {
            return;
        }

        $origin = $this->lockInventory($transfer->from_branch_id, $item->product_id);
        $origin->forceFill([
            'quantity_in_transfer' => max(0, $origin->quantity_in_transfer - $remaining),
        ])->save();
    }

    public function markPooledLost(BranchTransfer $transfer, BranchTransferItem $item): void
    {
        $remaining = max(0, $item->quantity - $item->received_quantity);
        if ($remaining === 0) {
            return;
        }

        $origin = $this->lockInventory($transfer->from_branch_id, $item->product_id);
        if ($origin->quantity_on_hand < $remaining || $origin->quantity_in_transfer < $remaining) {
            throw ValidationException::withMessages([
                'item' => 'Stok asal tidak konsisten untuk ditandai hilang.',
            ]);
        }
        $origin->forceFill([
            'quantity_on_hand' => $origin->quantity_on_hand - $remaining,
            'quantity_in_transfer' => $origin->quantity_in_transfer - $remaining,
        ])->save();
    }

    public function syncSerialized(int $productId, int $branchId): void
    {
        $inventory = BranchInventory::query()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $productId],
            [
                'quantity_on_hand' => 0,
                'quantity_reserved' => 0,
                'quantity_rented' => 0,
                'quantity_maintenance' => 0,
                'quantity_in_transfer' => 0,
                'reorder_level' => 0,
            ],
        );

        $base = Asset::query()
            ->where('product_id', $productId)
            ->where('current_branch_id', $branchId)
            ->where('is_active', true);

        $inventory->forceFill([
            'quantity_on_hand' => (clone $base)->count(),
            'quantity_maintenance' => (clone $base)->where('status', 'maintenance')->count(),
            'quantity_in_transfer' => (clone $base)->where('status', 'in_transit')->count(),
        ])->save();
    }

    private function lockInventory(int $branchId, int $productId, bool $create = false): BranchInventory
    {
        $query = BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate();
        $inventory = $query->first();

        if ($inventory !== null) {
            return $inventory;
        }

        if (! $create) {
            throw ValidationException::withMessages([
                'transfer' => 'Data inventory cabang tidak ditemukan.',
            ]);
        }

        BranchInventory::query()->create([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'quantity_on_hand' => 0,
            'quantity_reserved' => 0,
            'quantity_rented' => 0,
            'quantity_maintenance' => 0,
            'quantity_in_transfer' => 0,
            'reorder_level' => 0,
        ]);

        return BranchInventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function availableQuantity(BranchInventory $inventory): int
    {
        return max(0,
            $inventory->quantity_on_hand
            - $inventory->quantity_reserved
            - $inventory->quantity_rented
            - $inventory->quantity_maintenance
            - $inventory->quantity_in_transfer,
        );
    }
}
