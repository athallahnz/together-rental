<?php

namespace App\Domain\Transfers;

use App\Domain\Transfers\Enums\TransferStatus;
use App\Models\Asset;
use App\Models\BranchInventory;
use App\Models\BranchTransfer;
use App\Models\BranchTransferItem;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TransferEligibilityService
{
    /**
     * @return list<array{code: string, field: string, message: string, asset_id?: int, asset_code?: string, source_type?: string, source_id?: int, source_number?: string, starts_at?: string|null, ends_at?: string|null}>
     */
    public function blockers(
        BranchTransfer $transfer,
        bool $forDispatch = false,
        ?BranchTransfer $contextTransfer = null,
    ): array {
        $transfer->loadMissing(['items.asset', 'items.product']);
        $contextTransfer?->loadMissing('items');
        $blockers = [];

        if ($transfer->planned_dispatch_at === null) {
            $blockers[] = [
                'code' => 'DISPATCH_SCHEDULE_REQUIRED',
                'field' => 'planned_dispatch_at',
                'message' => 'Jadwal keberangkatan wajib ditentukan sebelum transfer diproses.',
            ];

            return $blockers;
        }

        foreach ($transfer->items as $item) {
            if ($item->asset_id !== null) {
                $blockers = [
                    ...$blockers,
                    ...$this->serializedBlockers(
                        $transfer,
                        $item,
                        $forDispatch,
                        $contextTransfer,
                    ),
                ];

                continue;
            }

            $blockers = [
                ...$blockers,
                ...$this->pooledBlockers(
                    $transfer,
                    $item,
                    $forDispatch,
                    $contextTransfer,
                ),
            ];
        }

        return $blockers;
    }

    public function assertEligible(
        BranchTransfer $transfer,
        bool $forDispatch = false,
        ?BranchTransfer $contextTransfer = null,
    ): void {
        $blockers = $this->blockers($transfer, $forDispatch, $contextTransfer);

        if ($blockers === []) {
            return;
        }

        throw ValidationException::withMessages([
            'transfer' => collect($blockers)->pluck('message')->unique()->values()->all(),
        ]);
    }

    /**
     * @return list<array{code: string, field: string, message: string, asset_id?: int, asset_code?: string, source_type?: string, source_id?: int, source_number?: string, starts_at?: string|null, ends_at?: string|null}>
     */
    private function serializedBlockers(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        bool $forDispatch,
        ?BranchTransfer $contextTransfer,
    ): array {
        $asset = $item->asset;

        if ($asset === null) {
            return [[
                'code' => 'ASSET_NOT_FOUND',
                'field' => "items.{$item->line_number}.asset_id",
                'message' => 'Aset pada item transfer tidak ditemukan.',
            ]];
        }

        $blockers = [];
        $heldByContext = $this->contextHoldsAsset($contextTransfer, $asset->id);
        $allowedStatuses = $forDispatch
            ? ['in_transit']
            : ($heldByContext ? ['available', 'in_transit'] : ['available']);

        if (! $asset->is_active) {
            $blockers[] = $this->assetBlocker($asset, 'ASSET_INACTIVE', 'Aset tidak aktif.');
        }

        if ($asset->current_branch_id !== $transfer->from_branch_id) {
            $blockers[] = $this->assetBlocker($asset, 'WRONG_BRANCH', 'Aset tidak berada di cabang asal transfer.');
        }

        if ($asset->product_id !== $item->product_id) {
            $blockers[] = $this->assetBlocker($asset, 'PRODUCT_MISMATCH', 'Produk aset tidak sesuai dengan item transfer.');
        }

        if (! in_array($asset->status, $allowedStatuses, true)) {
            $message = $forDispatch
                ? 'Aset tidak lagi berada pada status in transit.'
                : 'Aset harus berstatus tersedia sebelum transfer disetujui.';
            $blockers[] = $this->assetBlocker($asset, 'INVALID_STATUS', $message);
        }

        if (in_array($asset->condition, ['lost', 'retired'], true)) {
            $blockers[] = $this->assetBlocker($asset, 'INVALID_CONDITION', 'Kondisi aset tidak memungkinkan transfer.');
        }

        $maintenance = MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->whereIn('status', ['reported', 'in_progress'])
            ->first(['id', 'maintenance_number']);
        if ($maintenance !== null) {
            $blockers[] = [
                ...$this->assetBlocker($asset, 'MAINTENANCE_ACTIVE', 'Aset masih memiliki maintenance aktif.'),
                'source_type' => 'maintenance',
                'source_id' => $maintenance->id,
                'source_number' => $maintenance->maintenance_number,
            ];
        }

        $rental = DB::table('rental_item_assets')
            ->join('rental_items', 'rental_items.id', '=', 'rental_item_assets.rental_item_id')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->where('rental_item_assets.asset_id', $asset->id)
            ->whereNull('rental_item_assets.returned_at')
            ->whereNotIn('rentals.status', ['returned', 'cancelled', 'void'])
            ->first(['rentals.id', 'rentals.rental_number', 'rentals.checked_out_at', 'rentals.due_at']);
        if ($rental !== null) {
            $blockers[] = [
                ...$this->assetBlocker($asset, 'RENTAL_ACTIVE', 'Aset masih berada pada rental aktif.'),
                'source_type' => 'rental',
                'source_id' => (int) $rental->id,
                'source_number' => (string) $rental->rental_number,
                'starts_at' => $rental->checked_out_at,
                'ends_at' => $rental->due_at,
            ];
        }

        $plannedDispatch = Carbon::parse($transfer->planned_dispatch_at);
        $reservation = DB::table('asset_reservations')
            ->join('bookings', 'bookings.id', '=', 'asset_reservations.booking_id')
            ->where('asset_reservations.asset_id', $asset->id)
            ->where('asset_reservations.status', 'reserved')
            ->where('asset_reservations.ends_at', '>', $plannedDispatch)
            ->first([
                'bookings.id',
                'bookings.booking_number',
                'asset_reservations.starts_at',
                'asset_reservations.ends_at',
            ]);
        if ($reservation !== null) {
            $blockers[] = [
                ...$this->assetBlocker($asset, 'BOOKING_CONFLICT', 'Aset memiliki booking yang masih membutuhkan unit setelah jadwal keberangkatan.'),
                'source_type' => 'booking',
                'source_id' => (int) $reservation->id,
                'source_number' => (string) $reservation->booking_number,
                'starts_at' => $reservation->starts_at,
                'ends_at' => $reservation->ends_at,
            ];
        }

        $excludedTransferIds = [$transfer->id];
        if ($contextTransfer !== null && $contextTransfer->id !== $transfer->id) {
            $excludedTransferIds[] = $contextTransfer->id;
        }

        $otherTransfer = DB::table('branch_transfer_items')
            ->join('branch_transfers', 'branch_transfers.id', '=', 'branch_transfer_items.branch_transfer_id')
            ->where('branch_transfer_items.asset_id', $asset->id)
            ->whereNotIn('branch_transfers.id', $excludedTransferIds)
            ->whereIn('branch_transfers.status', [
                TransferStatus::Approved->value,
                TransferStatus::Dispatched->value,
                TransferStatus::Receiving->value,
                TransferStatus::Discrepancy->value,
            ])
            ->first(['branch_transfers.id', 'branch_transfers.transfer_number']);
        if ($otherTransfer !== null) {
            $blockers[] = [
                ...$this->assetBlocker($asset, 'TRANSFER_ACTIVE', 'Aset masih berada pada transfer aktif lain.'),
                'source_type' => 'transfer',
                'source_id' => (int) $otherTransfer->id,
                'source_number' => (string) $otherTransfer->transfer_number,
            ];
        }

        if (Schema::hasTable('rental_operational_corrections')) {
            $correction = DB::table('rental_operational_corrections')
                ->join('rental_returns', 'rental_returns.id', '=', 'rental_operational_corrections.original_return_id')
                ->join('rental_return_items', 'rental_return_items.rental_return_id', '=', 'rental_returns.id')
                ->where('rental_return_items.asset_id', $asset->id)
                ->where('rental_operational_corrections.status', 'open')
                ->first([
                    'rental_operational_corrections.id',
                    'rental_operational_corrections.correction_number',
                ]);
            if ($correction !== null) {
                $blockers[] = [
                    ...$this->assetBlocker($asset, 'CORRECTION_ACTIVE', 'Aset masih berada pada koreksi operasional aktif.'),
                    'source_type' => 'operational_correction',
                    'source_id' => (int) $correction->id,
                    'source_number' => (string) $correction->correction_number,
                ];
            }
        }

        return $blockers;
    }

    /**
     * @return list<array{code: string, field: string, message: string}>
     */
    private function pooledBlockers(
        BranchTransfer $transfer,
        BranchTransferItem $item,
        bool $forDispatch,
        ?BranchTransfer $contextTransfer,
    ): array {
        $inventory = BranchInventory::query()
            ->where('branch_id', $transfer->from_branch_id)
            ->where('product_id', $item->product_id)
            ->first();

        if ($inventory === null) {
            return [[
                'code' => 'INVENTORY_NOT_FOUND',
                'field' => "items.{$item->line_number}.quantity",
                'message' => 'Stok produk pada cabang asal tidak ditemukan.',
            ]];
        }

        $heldByCurrentTransfer = $forDispatch ? $item->quantity : 0;
        $heldByContext = $forDispatch
            ? 0
            : $this->contextHeldQuantity($contextTransfer, $item->product_id);
        $available = max(0,
            $inventory->quantity_on_hand
            - $inventory->quantity_reserved
            - $inventory->quantity_rented
            - $inventory->quantity_maintenance
            - $inventory->quantity_in_transfer
            + $heldByCurrentTransfer
            + $heldByContext,
        );

        if ($available < $item->quantity) {
            return [[
                'code' => 'INSUFFICIENT_STOCK',
                'field' => "items.{$item->line_number}.quantity",
                'message' => "Stok tersedia {$available} unit, sedangkan transfer membutuhkan {$item->quantity} unit.",
            ]];
        }

        return [];
    }

    private function contextHoldsAsset(?BranchTransfer $contextTransfer, int $assetId): bool
    {
        if ($contextTransfer === null || ! $contextTransfer->status->holdsInventory()) {
            return false;
        }

        return $contextTransfer->items->contains(
            static fn (BranchTransferItem $item): bool => $item->asset_id === $assetId,
        );
    }

    private function contextHeldQuantity(?BranchTransfer $contextTransfer, int $productId): int
    {
        if ($contextTransfer === null || ! $contextTransfer->status->holdsInventory()) {
            return 0;
        }

        return $contextTransfer->items
            ->filter(
                static fn (BranchTransferItem $item): bool => $item->asset_id === null
                    && $item->product_id === $productId,
            )
            ->sum(
                static fn (BranchTransferItem $item): int => max(
                    0,
                    $item->quantity - $item->received_quantity,
                ),
            );
    }

    /** @return array{code: string, field: string, message: string, asset_id: int, asset_code: string} */
    private function assetBlocker(Asset $asset, string $code, string $message): array
    {
        return [
            'code' => $code,
            'field' => 'items',
            'message' => "{$asset->asset_code}: {$message}",
            'asset_id' => $asset->id,
            'asset_code' => $asset->asset_code,
        ];
    }
}
