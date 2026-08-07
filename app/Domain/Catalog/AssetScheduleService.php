<?php

namespace App\Domain\Catalog;

use App\Models\Asset;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AssetScheduleService
{
    /**
     * @return array{
     *     asset: array<string, mixed>,
     *     range: array{starts_at: string, ends_at: string},
     *     events: list<array<string, mixed>>
     * }
     */
    public function calendar(
        Asset $asset,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $public = false,
    ): array {
        $asset->loadMissing('currentBranch:id,code,name,city,timezone');

        $events = [
            ...$this->bookingEvents($asset, $startsAt, $endsAt, $public),
            ...$this->rentalEvents($asset, $startsAt, $endsAt, $public),
            ...$this->maintenanceEvents($asset, $startsAt, $endsAt, $public),
            ...$this->transferEvents($asset, $startsAt, $endsAt, $public),
        ];

        usort(
            $events,
            static fn (array $first, array $second): int => strcmp(
                (string) $first['starts_at'],
                (string) $second['starts_at'],
            ),
        );

        return [
            'asset' => [
                'id' => $public ? null : $asset->id,
                'asset_code' => $public ? null : $asset->asset_code,
                'serial_number' => $public ? null : $asset->serial_number,
                'status' => $asset->status,
                'condition' => $asset->condition,
                'branch' => $asset->currentBranch === null ? null : [
                    'id' => $asset->currentBranch->id,
                    'code' => $asset->currentBranch->code,
                    'name' => $asset->currentBranch->name,
                    'city' => $asset->currentBranch->city,
                ],
            ],
            'range' => [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ],
            'events' => $events,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function bookingEvents(
        Asset $asset,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $public,
    ): array {
        return array_values(DB::table('asset_reservations')
            ->join('bookings', 'bookings.id', '=', 'asset_reservations.booking_id')
            ->where('asset_reservations.asset_id', $asset->id)
            ->where('asset_reservations.status', 'reserved')
            ->whereNull('bookings.deleted_at')
            ->whereIn('bookings.status', ['confirmed'])
            ->where('asset_reservations.starts_at', '<=', $endsAt->toDateTimeString())
            ->where('asset_reservations.ends_at', '>=', $startsAt->toDateTimeString())
            ->orderBy('asset_reservations.starts_at')
            ->get([
                'asset_reservations.id',
                'asset_reservations.starts_at',
                'asset_reservations.ends_at',
                'bookings.id as booking_id',
                'bookings.booking_number',
            ])
            ->map(fn (object $row): array => [
                'id' => "booking:{$row->id}",
                'type' => 'booking',
                'status' => 'booked',
                'label' => $public ? 'Terbooking' : "Booking {$row->booking_number}",
                'starts_at' => CarbonImmutable::parse((string) $row->starts_at)->toIso8601String(),
                'ends_at' => CarbonImmutable::parse((string) $row->ends_at)->toIso8601String(),
                'is_open_ended' => false,
                'reference' => $public ? null : (string) $row->booking_number,
                'href' => $public ? null : "/bookings/{$row->booking_id}",
            ])
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function rentalEvents(
        Asset $asset,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $public,
    ): array {
        $extensions = DB::table('rental_extensions')
            ->selectRaw('rental_id, MAX(extended_due_at) AS extended_due_at')
            ->whereIn('status', ['approved', 'completed'])
            ->groupBy('rental_id');

        return array_values(DB::table('rental_item_assets')
            ->join('rental_items', 'rental_items.id', '=', 'rental_item_assets.rental_item_id')
            ->join('rentals', 'rentals.id', '=', 'rental_items.rental_id')
            ->leftJoinSub(
                $extensions,
                'rental_extensions_max',
                'rental_extensions_max.rental_id',
                '=',
                'rentals.id',
            )
            ->where('rental_item_assets.asset_id', $asset->id)
            ->whereNull('rentals.deleted_at')
            ->whereNotIn('rentals.status', ['draft', 'cancelled', 'void', 'rejected'])
            ->where(function (Builder $query) use ($endsAt): void {
                $query
                    ->whereNull('rental_item_assets.checked_out_at')
                    ->orWhere('rental_item_assets.checked_out_at', '<=', $endsAt->toDateTimeString());
            })
            ->where(function (Builder $query) use ($startsAt): void {
                $query
                    ->whereNull('rental_item_assets.returned_at')
                    ->orWhere('rental_item_assets.returned_at', '>=', $startsAt->toDateTimeString());
            })
            ->orderBy('rentals.checked_out_at')
            ->get([
                'rental_item_assets.id',
                'rental_item_assets.checked_out_at as asset_checked_out_at',
                'rental_item_assets.returned_at as asset_returned_at',
                'rentals.id as rental_id',
                'rentals.rental_number',
                'rentals.status as rental_status',
                'rentals.checked_out_at',
                'rentals.due_at',
                'rentals.returned_at',
                'rental_extensions_max.extended_due_at',
            ])
            ->map(function (object $row) use ($startsAt, $endsAt, $public): ?array {
                $startValue = $row->asset_checked_out_at ?? $row->checked_out_at;

                if ($startValue === null) {
                    return null;
                }

                $start = CarbonImmutable::parse((string) $startValue);
                $due = CarbonImmutable::parse((string) ($row->extended_due_at ?? $row->due_at));
                $returnedValue = $row->asset_returned_at ?? $row->returned_at;
                $isOpen = $returnedValue === null
                    && in_array((string) $row->rental_status, [
                        'active',
                        'partial_return',
                        'correction_pending',
                    ], true)
                    && $due->isPast();
                $end = $returnedValue !== null
                    ? CarbonImmutable::parse((string) $returnedValue)
                    : ($isOpen ? $endsAt : $due);

                if ($start->isAfter($endsAt) || $end->isBefore($startsAt)) {
                    return null;
                }

                return [
                    'id' => "rental:{$row->id}",
                    'type' => 'rental',
                    'status' => 'rented',
                    'label' => $public ? 'Tersewa' : "Rental {$row->rental_number}",
                    'starts_at' => $start->toIso8601String(),
                    'ends_at' => $end->toIso8601String(),
                    'is_open_ended' => $isOpen,
                    'is_overdue' => $isOpen,
                    'reference' => $public ? null : (string) $row->rental_number,
                    'href' => $public ? null : "/rentals/{$row->rental_id}",
                ];
            })
            ->filter()
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function maintenanceEvents(
        Asset $asset,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $public,
    ): array {
        return array_values(DB::table('maintenance_orders')
            ->where('asset_id', $asset->id)
            ->where('reported_at', '<=', $endsAt->toDateTimeString())
            ->where(function (Builder $query) use ($startsAt): void {
                $query
                    ->whereNull('completed_at')
                    ->orWhere('completed_at', '>=', $startsAt->toDateTimeString());
            })
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->orderBy('reported_at')
            ->get([
                'id',
                'maintenance_number',
                'status',
                'reported_at',
                'completed_at',
            ])
            ->map(function (object $row) use ($endsAt, $public): array {
                $open = $row->completed_at === null
                    && ! in_array((string) $row->status, ['completed', 'closed'], true);

                return [
                    'id' => "maintenance:{$row->id}",
                    'type' => 'maintenance',
                    'status' => 'maintenance',
                    'label' => $public ? 'Perawatan' : "Maintenance {$row->maintenance_number}",
                    'starts_at' => CarbonImmutable::parse((string) $row->reported_at)->toIso8601String(),
                    'ends_at' => ($row->completed_at !== null
                        ? CarbonImmutable::parse((string) $row->completed_at)
                        : $endsAt)->toIso8601String(),
                    'is_open_ended' => $open,
                    'reference' => $public ? null : (string) $row->maintenance_number,
                    'href' => $public ? null : "/maintenance/{$row->id}",
                ];
            })
            ->values()
            ->all());
    }

    /** @return list<array<string, mixed>> */
    private function transferEvents(
        Asset $asset,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        bool $public,
    ): array {
        return array_values(DB::table('branch_transfer_items')
            ->join('branch_transfers', 'branch_transfers.id', '=', 'branch_transfer_items.branch_transfer_id')
            ->where('branch_transfer_items.asset_id', $asset->id)
            ->whereNotIn('branch_transfers.status', ['draft', 'pending_approval', 'rejected', 'cancelled'])
            ->where(function (Builder $query) use ($endsAt): void {
                $query
                    ->whereNull('branch_transfers.approved_at')
                    ->orWhere('branch_transfers.approved_at', '<=', $endsAt->toDateTimeString());
            })
            ->orderBy('branch_transfers.approved_at')
            ->get([
                'branch_transfer_items.id',
                'branch_transfers.id as transfer_id',
                'branch_transfers.transfer_number',
                'branch_transfers.status',
                'branch_transfers.approved_at',
                'branch_transfers.planned_dispatch_at',
                'branch_transfers.expected_arrival_at',
                'branch_transfers.shipped_at',
                'branch_transfers.received_at',
                'branch_transfers.completed_at',
            ])
            ->map(function (object $row) use ($startsAt, $endsAt, $public): ?array {
                $startValue = $row->approved_at ?? $row->planned_dispatch_at ?? $row->shipped_at;

                if ($startValue === null) {
                    return null;
                }

                $start = CarbonImmutable::parse((string) $startValue);
                $endValue = $row->received_at ?? $row->completed_at ?? $row->expected_arrival_at;
                $isOpen = $endValue === null
                    && in_array((string) $row->status, [
                        'approved',
                        'dispatched',
                        'receiving',
                        'discrepancy',
                    ], true);
                $end = $endValue !== null
                    ? CarbonImmutable::parse((string) $endValue)
                    : $endsAt;

                if ($start->isAfter($endsAt) || $end->isBefore($startsAt)) {
                    return null;
                }

                return [
                    'id' => "transfer:{$row->id}",
                    'type' => 'transfer',
                    'status' => 'in_transit',
                    'label' => $public ? 'Dalam perpindahan' : "Transfer {$row->transfer_number}",
                    'starts_at' => $start->toIso8601String(),
                    'ends_at' => $end->toIso8601String(),
                    'is_open_ended' => $isOpen,
                    'reference' => $public ? null : (string) $row->transfer_number,
                    'href' => $public ? null : "/transfers/{$row->transfer_id}",
                ];
            })
            ->filter()
            ->values()
            ->all());
    }
}
