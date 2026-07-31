<?php

namespace App\Domain\Rentals;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalItemAsset;
use App\Models\RentalOperationalCorrection;
use App\Models\RentalReturn;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RentalOperationalCorrectionManager
{
    /** @param array<string, mixed> $data */
    public function reopen(Rental $rental, array $data, User $actor): RentalOperationalCorrection
    {
        return DB::transaction(function () use ($rental, $data, $actor): RentalOperationalCorrection {
            $locked = Rental::query()->lockForUpdate()->findOrFail($rental->id);

            if (! in_array($locked->status, ['returned', 'completed'], true)) {
                throw new ConflictHttpException('Hanya rental selesai yang dapat dibuka kembali.');
            }

            if (RentalOperationalCorrection::query()
                ->where('rental_id', $locked->id)
                ->where('status', 'open')
                ->exists()) {
                throw new ConflictHttpException('Rental masih memiliki koreksi operasional terbuka.');
            }

            $originalReturn = RentalReturn::query()
                ->with(['items'])
                ->where('rental_id', $locked->id)
                ->whereKey($data['rental_return_id'])
                ->where('status', 'completed')
                ->lockForUpdate()
                ->first();

            if ($originalReturn === null) {
                throw ValidationException::withMessages([
                    'rental_return_id' => 'Return tidak tersedia atau sudah pernah dikoreksi.',
                ]);
            }

            $assetIds = $originalReturn->items->pluck('asset_id')->filter()->values();
            $assignments = RentalItemAsset::query()
                ->whereHas('rentalItem', fn ($query) => $query->where('rental_id', $locked->id))
                ->whereIn('asset_id', $assetIds)
                ->with('asset')
                ->lockForUpdate()
                ->get();

            if ($assignments->count() !== $assetIds->count()) {
                throw new ConflictHttpException('Relasi unit return tidak lagi konsisten.');
            }

            foreach ($assignments as $assignment) {
                $this->guardAssetCanReopen($locked, $originalReturn, $assignment);
            }

            $correction = RentalOperationalCorrection::query()->create([
                'branch_id' => $locked->branch_id,
                'rental_id' => $locked->id,
                'original_return_id' => $originalReturn->id,
                'correction_number' => $this->nextNumber($locked),
                'status' => 'open',
                'reason' => $data['reason'],
                'snapshot_before' => $this->snapshot($locked, $originalReturn, $assignments),
                'opened_by' => $actor->id,
                'opened_at' => now(),
            ]);

            foreach ($assignments as $assignment) {
                $asset = $assignment->asset;
                $previousStatus = $asset->status;
                $previousCondition = $asset->condition;

                MaintenanceOrder::query()
                    ->where('asset_id', $asset->id)
                    ->where('status', 'reported')
                    ->update([
                        'status' => 'cancelled',
                        'resolution' => "Dibatalkan otomatis oleh {$correction->correction_number}.",
                        'updated_at' => now(),
                    ]);

                $assignment->update([
                    'return_condition' => null,
                    'returned_at' => null,
                    'status' => 'out',
                ]);
                $asset->update([
                    'status' => 'rented',
                    'condition' => $assignment->checkout_condition,
                ]);
                $this->recordAssetHistory(
                    $asset,
                    $correction,
                    $previousStatus,
                    $previousCondition,
                    $actor,
                );
                $this->syncMaintenanceInventory($asset);
            }

            $this->refreshItemStatuses($locked);
            $from = $locked->status;
            $locked->update([
                'status' => 'correction_pending',
                'returned_at' => null,
                'updated_by' => $actor->id,
            ]);
            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => 'correction_pending',
                'reason' => "{$correction->correction_number}: {$correction->reason}",
                'changed_by' => $actor->id,
                'changed_at' => now(),
            ]);

            return $correction->fresh();
        }, 3);
    }

    public function openSession(Rental $rental): ?RentalOperationalCorrection
    {
        return RentalOperationalCorrection::query()
            ->where('rental_id', $rental->id)
            ->where('status', 'open')
            ->with('originalReturn.items')
            ->lockForUpdate()
            ->first();
    }

    public function finalize(
        RentalOperationalCorrection $correction,
        RentalReturn $replacement,
        Rental $rental,
        User $actor,
    ): void {
        $original = RentalReturn::query()->lockForUpdate()
            ->findOrFail($correction->original_return_id);
        $original->update(['status' => 'superseded']);
        $correction->update([
            'replacement_return_id' => $replacement->id,
            'status' => 'completed',
            'snapshot_after' => $this->snapshot(
                $rental->fresh(),
                $replacement,
                RentalItemAsset::query()
                    ->whereHas('rentalItem', fn ($query) => $query->where('rental_id', $rental->id))
                    ->whereIn('asset_id', $replacement->items()->pluck('asset_id'))
                    ->with('asset')
                    ->get(),
            ),
            'finalized_by' => $actor->id,
            'finalized_at' => now(),
        ]);

    }

    private function guardAssetCanReopen(
        Rental $rental,
        RentalReturn $return,
        RentalItemAsset $assignment,
    ): void {
        $asset = $assignment->asset;

        if ($asset->current_branch_id !== $rental->branch_id) {
            throw ValidationException::withMessages([
                'rental_return_id' => "Aset {$asset->asset_code} sudah berpindah cabang.",
            ]);
        }

        $usedAgain = RentalItemAsset::query()
            ->where('asset_id', $asset->id)
            ->where('id', '!=', $assignment->id)
            ->where('checked_out_at', '>', $return->returned_at)
            ->exists();

        if ($usedAgain) {
            throw ValidationException::withMessages([
                'rental_return_id' => "Aset {$asset->asset_code} sudah digunakan pada rental berikutnya.",
            ]);
        }

        $transferred = DB::table('branch_transfer_items')
            ->join('branch_transfers', 'branch_transfers.id', '=', 'branch_transfer_items.branch_transfer_id')
            ->where('branch_transfer_items.asset_id', $asset->id)
            ->where('branch_transfers.created_at', '>', $return->returned_at)
            ->whereIn('branch_transfers.status', [
                'approved',
                'dispatched',
                'receiving',
                'discrepancy',
                'completed',
            ])
            ->exists();

        if ($transferred) {
            throw ValidationException::withMessages([
                'rental_return_id' => "Aset {$asset->asset_code} memiliki histori transfer setelah return.",
            ]);
        }

        $advancedMaintenance = MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->whereIn('status', ['in_progress', 'completed'])
            ->where('reported_at', '>=', $return->returned_at)
            ->exists();

        if ($advancedMaintenance) {
            throw ValidationException::withMessages([
                'rental_return_id' => "Maintenance aset {$asset->asset_code} sudah dimulai atau selesai.",
            ]);
        }
    }

    private function nextNumber(Rental $rental): string
    {
        DB::table('branches')->where('id', $rental->branch_id)->lockForUpdate()->first();
        $branchCode = DB::table('branches')->where('id', $rental->branch_id)->value('code');
        $prefix = 'ROC-'.$branchCode.'-'.now()->format('ymd').'-';
        $last = RentalOperationalCorrection::query()
            ->where('branch_id', $rental->branch_id)
            ->where('correction_number', 'like', $prefix.'%')
            ->orderByDesc('correction_number')
            ->value('correction_number');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function refreshItemStatuses(Rental $rental): void
    {
        RentalItem::query()->where('rental_id', $rental->id)->get()
            ->each(function (RentalItem $item): void {
                $returned = $item->assets()->whereIn('status', ['returned', 'lost'])->count();
                $item->update([
                    'returned_quantity' => $returned,
                    'status' => match (true) {
                        $returned === 0 => 'out',
                        $returned >= $item->quantity => 'returned',
                        default => 'partial_return',
                    },
                ]);
            });
    }

    private function syncMaintenanceInventory(Asset $asset): void
    {
        $quantity = Asset::query()
            ->where('current_branch_id', $asset->current_branch_id)
            ->where('product_id', $asset->product_id)
            ->where('status', 'maintenance')
            ->count();

        DB::table('branch_inventories')->updateOrInsert(
            ['branch_id' => $asset->current_branch_id, 'product_id' => $asset->product_id],
            ['quantity_maintenance' => $quantity, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    private function recordAssetHistory(
        Asset $asset,
        RentalOperationalCorrection $correction,
        string $fromStatus,
        string $fromCondition,
        User $actor,
    ): void {
        DB::table('asset_status_histories')->insert([
            'asset_id' => $asset->id,
            'branch_id' => $asset->current_branch_id,
            'from_status' => $fromStatus,
            'to_status' => 'rented',
            'from_condition' => $fromCondition,
            'to_condition' => $asset->condition,
            'source_type' => RentalOperationalCorrection::class,
            'source_id' => $correction->id,
            'reason' => "Return dibuka kembali melalui {$correction->correction_number}.",
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  EloquentCollection<int, RentalItemAsset>  $assignments
     * @return array<string, mixed>
     */
    private function snapshot(
        Rental $rental,
        RentalReturn $return,
        EloquentCollection $assignments,
    ): array {
        return [
            'rental' => $rental->only([
                'id', 'status', 'returned_at', 'balance_due', 'paid_amount',
                'late_fee_amount', 'damage_fee_amount',
            ]),
            'return' => $return->only([
                'id', 'return_number', 'status', 'returned_at', 'notes',
            ]),
            'units' => $assignments->map(fn (RentalItemAsset $assignment): array => [
                'rental_item_asset_id' => $assignment->id,
                'asset_id' => $assignment->asset_id,
                'asset_code' => $assignment->asset->asset_code,
                'assignment_status' => $assignment->status,
                'return_condition' => $assignment->return_condition,
                'asset_status' => $assignment->asset->status,
                'asset_condition' => $assignment->asset->condition,
            ])->values()->all(),
        ];
    }
}
