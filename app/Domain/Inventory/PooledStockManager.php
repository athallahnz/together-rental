<?php

namespace App\Domain\Inventory;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BranchInventory;
use App\Models\BulkReservation;
use App\Models\RentalItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PooledStockManager
{
    public function lock(int $branchId, int $productId): BranchInventory
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Pooled stock mutations require a transaction.');
        }

        $inventory = BranchInventory::query()->where('branch_id', $branchId)
            ->where('product_id', $productId)->lockForUpdate()->first();
        if ($inventory === null) {
            throw ValidationException::withMessages(['items' => 'Stok produk pada cabang ini belum tersedia.']);
        }

        return $inventory;
    }

    public function available(
        int $branchId,
        int $productId,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        ?int $ignoreBookingId = null,
    ): int {
        $inventory = BranchInventory::query()->where('branch_id', $branchId)
            ->where('product_id', $productId)->first();

        return $inventory === null ? 0 : max(0, $this->remaining(
            $inventory, $startsAt->getTimestamp(), $endsAt->getTimestamp(), $ignoreBookingId,
        ));
    }

    /**
     * The inventory row is the mutex for booking, checkout, return, extension,
     * transfer and stock adjustment. Mutating callers must lock it BEFORE reading
     * demand. Locking reads also avoid an old MySQL REPEATABLE READ snapshot.
     *
     * @param  list<int>  $ignoreRentalItemIds
     */
    public function remaining(
        BranchInventory $inventory,
        int $start,
        int $end = PHP_INT_MAX,
        ?int $ignoreBookingId = null,
        array $ignoreRentalItemIds = [],
    ): int {
        return $this->projection($inventory, $start, $end, $ignoreBookingId, $ignoreRentalItemIds)['remaining'];
    }

    /**
     * @param  list<int>  $ignoreRentalItemIds
     * @return array{capacity: int, reserved: int, rented: int, remaining: int}
     */
    public function projection(
        BranchInventory $inventory,
        int $start,
        int $end = PHP_INT_MAX,
        ?int $ignoreBookingId = null,
        array $ignoreRentalItemIds = [],
    ): array {
        $reservations = $this->reservations($inventory);
        $reservationIntervals = $reservations->map(fn (BulkReservation $row): array => [
            'start' => $row->starts_at->getTimestamp(),
            'end' => $row->ends_at->getTimestamp(),
            'quantity' => $row->quantity,
        ])->values()->all();
        $rented = DB::table('rental_items')->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->where('rentals.branch_id', $inventory->branch_id)
            ->where('rental_items.product_id', $inventory->product_id)
            ->where('rental_items.is_bulk', true)
            ->whereColumn('rental_items.quantity', '>', 'rental_items.returned_quantity')
            ->whereIn('rentals.status', ['active', 'partial_return', 'correction_pending'])
            ->whereNull('rentals.deleted_at')
            ->orderBy('rental_items.id')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->get(['rental_items.id', 'rental_items.quantity', 'rental_items.returned_quantity',
                'rentals.checked_out_at', DB::raw('COALESCE(rental_items.due_at, rentals.due_at) as due_at')]);
        $knownRented = (int) $rented->sum(fn ($row): int => $row->quantity - $row->returned_quantity);

        // Imported/manual counters with no ledger remain blocked; never silently
        // reset them or assume those units can be promised to a customer.
        $untrackedReserved = max(0, $inventory->quantity_reserved - QuantityTimeline::peak($reservationIntervals));
        $untrackedRented = max(0, $inventory->quantity_rented - $knownRented);
        $intervals = $reservations->reject(fn (BulkReservation $row): bool => $row->booking_id === $ignoreBookingId)
            ->map(fn (BulkReservation $row): array => [
                'start' => $row->starts_at->getTimestamp(),
                'end' => $row->ends_at->getTimestamp(),
                'quantity' => $row->quantity,
            ])->values()->all();
        $reservedPeak = QuantityTimeline::peak($intervals, $start, $end);
        $rentalIntervals = [];
        foreach ($rented as $row) {
            if (in_array((int) $row->id, $ignoreRentalItemIds, true)) {
                continue;
            }
            $due = CarbonImmutable::parse($row->due_at);
            $rentalIntervals[] = [
                'start' => $row->checked_out_at === null ? PHP_INT_MIN : CarbonImmutable::parse($row->checked_out_at)->getTimestamp(),
                // Overdue stock remains unavailable until it is actually returned.
                'end' => $due->lessThanOrEqualTo(now()) ? PHP_INT_MAX : $due->getTimestamp(),
                'quantity' => (int) $row->quantity - (int) $row->returned_quantity,
            ];
        }

        $capacity = $inventory->quantity_on_hand - $inventory->quantity_maintenance - $inventory->quantity_in_transfer;

        return [
            'capacity' => max(0, $capacity),
            'reserved' => $untrackedReserved + $reservedPeak,
            'rented' => $untrackedRented + QuantityTimeline::peak($rentalIntervals, $start, $end),
            'remaining' => $capacity - $untrackedReserved - $untrackedRented
                - QuantityTimeline::peak([...$intervals, ...$rentalIntervals], $start, $end),
        ];
    }

    public function transferable(BranchInventory $inventory, int $heldCredit = 0): int
    {
        return max(0, min(
            $inventory->quantity_on_hand - $inventory->quantity_rented
                - $inventory->quantity_maintenance - $inventory->quantity_in_transfer + $heldCredit,
            $this->remaining($inventory, now()->getTimestamp()) + $heldCredit,
        ));
    }

    public function reserve(Booking $booking, BookingItem $item, int $productId, int $quantity, int $position): void
    {
        $inventory = $this->lock($booking->branch_id, $productId);
        $previousPeak = $this->reservedPeak($inventory);
        if ($quantity < 1 || $this->remaining(
            $inventory, $booking->starts_at->getTimestamp(), $booking->ends_at->getTimestamp(),
        ) < $quantity) {
            throw ValidationException::withMessages([
                "items.{$position}.quantity" => 'Stok Bulk tersedia tidak mencukupi pada periode yang dipilih.',
            ]);
        }
        BulkReservation::query()->create([
            'branch_id' => $booking->branch_id,
            'product_id' => $productId,
            'booking_id' => $booking->id,
            'booking_item_id' => $item->id,
            'quantity' => $quantity,
            'starts_at' => $booking->starts_at,
            'ends_at' => $booking->ends_at,
            'status' => 'reserved',
        ]);
        $this->syncReserved($inventory, $previousPeak);
    }

    public function release(Booking $booking, User $actor, string $reason, string $status = 'released'): void
    {
        $groups = $booking->bulkReservations()->where('status', 'reserved')
            ->orderBy('product_id')->lockForUpdate()->get()->groupBy('product_id');
        foreach ($groups as $productId => $rows) {
            $inventory = $this->lock($booking->branch_id, (int) $productId);
            $previousPeak = $this->reservedPeak($inventory);
            BulkReservation::query()->whereKey($rows->pluck('id'))->update([
                'status' => $status,
                'released_at' => now(),
                'released_by' => $actor->id,
                'release_reason' => $reason,
                'updated_at' => now(),
            ]);
            $this->syncReserved($inventory, $previousPeak);
        }
    }

    public function assertBooking(Booking $booking, ?CarbonImmutable $checkoutAt = null): void
    {
        $groups = $booking->bulkReservations()->where('status', 'reserved')
            ->orderBy('product_id')->lockForUpdate()->get()->groupBy('product_id');
        foreach ($groups as $productId => $rows) {
            $inventory = $this->lock($booking->branch_id, (int) $productId);
            $quantity = (int) $rows->sum('quantity');
            $start = $checkoutAt ?? CarbonImmutable::parse($booking->starts_at);
            $enoughPhysical = $inventory->quantity_on_hand - $inventory->quantity_rented
                - $inventory->quantity_maintenance - $inventory->quantity_in_transfer;
            if ($this->remaining($inventory, $start->getTimestamp(), $booking->ends_at->getTimestamp(), $booking->id) < $quantity
                || ($checkoutAt !== null && ($enoughPhysical < $quantity || ! $start->lessThan($booking->ends_at)))) {
                throw ValidationException::withMessages(['booking' => 'Stok Bulk tidak lagi mencukupi untuk booking ini. Periksa jadwal dan stok cabang.']);
            }
        }
    }

    public function returnQuantity(RentalItem $item, int $quantity, string $condition): void
    {
        $inventory = $this->lock($item->rental->branch_id, $item->product_id);
        if ($quantity < 1 || $quantity > $item->quantity - $item->returned_quantity
            || $inventory->quantity_rented < $quantity
            || ($condition === 'lost' && $inventory->quantity_on_hand < $quantity)) {
            throw ValidationException::withMessages(['bulk_items' => 'Jumlah pengembalian Bulk atau counter stok tidak valid.']);
        }
        $inventory->forceFill([
            'quantity_rented' => $inventory->quantity_rented - $quantity,
            'quantity_maintenance' => $inventory->quantity_maintenance + ($condition === 'damaged' ? $quantity : 0),
            'quantity_on_hand' => $inventory->quantity_on_hand - ($condition === 'lost' ? $quantity : 0),
        ])->save();
        $returned = $item->returned_quantity + $quantity;
        $item->update([
            'returned_quantity' => $returned,
            'status' => $returned === $item->quantity ? 'returned' : 'partial_return',
        ]);
    }

    private function reservedPeak(BranchInventory $inventory): int
    {
        return QuantityTimeline::peak($this->reservations($inventory)->map(fn (BulkReservation $row): array => [
            'start' => $row->starts_at->getTimestamp(),
            'end' => $row->ends_at->getTimestamp(),
            'quantity' => $row->quantity,
        ])->values()->all());
    }

    private function syncReserved(BranchInventory $inventory, int $previousPeak): void
    {
        $inventory->update(['quantity_reserved' => max(0, $inventory->quantity_reserved - $previousPeak) + $this->reservedPeak($inventory)]);
    }

    /** @return Collection<int, BulkReservation> */
    private function reservations(BranchInventory $inventory): Collection
    {
        return BulkReservation::query()->where('branch_id', $inventory->branch_id)
            ->where('product_id', $inventory->product_id)->where('status', 'reserved')
            ->orderBy('id')->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->get();
    }
}
