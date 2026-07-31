<?php

namespace App\Domain\Transfers;

use App\Models\BranchTransfer;

class TransferSnapshotFactory
{
    /** @return array<string, mixed> */
    public function make(BranchTransfer $transfer): array
    {
        $transfer->loadMissing([
            'originBranch:id,code,name',
            'destinationBranch:id,code,name',
            'items.product:id,sku,name,tracking_type',
            'items.asset:id,product_id,asset_code,serial_number,status,condition,current_branch_id',
        ]);

        return [
            'transfer_id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'revision_number' => $transfer->revision_number,
            'from_branch' => $transfer->originBranch?->only(['id', 'code', 'name']),
            'to_branch' => $transfer->destinationBranch?->only(['id', 'code', 'name']),
            'reason' => $transfer->reason,
            'planned_dispatch_at' => $transfer->planned_dispatch_at?->toISOString(),
            'expected_arrival_at' => $transfer->expected_arrival_at?->toISOString(),
            'items' => $transfer->items->map(fn ($item): array => [
                'id' => $item->id,
                'line_number' => $item->line_number,
                'product' => $item->product?->only(['id', 'sku', 'name', 'tracking_type']),
                'asset' => $item->asset?->only([
                    'id', 'asset_code', 'serial_number', 'status', 'condition', 'current_branch_id',
                ]),
                'quantity' => $item->quantity,
                'condition_before' => $item->condition_before,
                'notes' => $item->notes,
            ])->values()->all(),
        ];
    }

    /** @return array{snapshot: array<string, mixed>, hash: string} */
    public function makeWithHash(BranchTransfer $transfer): array
    {
        $snapshot = $this->make($transfer);
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['snapshot' => $snapshot, 'hash' => hash('sha256', $json)];
    }
}
