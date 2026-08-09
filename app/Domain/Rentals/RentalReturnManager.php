<?php

namespace App\Domain\Rentals;

use App\Domain\Finance\PaymentManager;
use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\MaintenanceOrder;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalItemAsset;
use App\Models\RentalOperationalCorrection;
use App\Models\RentalReturn;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RentalReturnManager
{
    public function __construct(
        private readonly RentalNumberGenerator $numbers,
        private readonly RentalOperationalCorrectionManager $corrections,
        private readonly PaymentManager $payments,
    ) {}

    /** @param array<string, mixed> $data */
    public function process(Rental $rental, array $data, User $actor): RentalReturn
    {
        return DB::transaction(function () use ($rental, $data, $actor): RentalReturn {
            $locked = Rental::query()
                ->with('branch')
                ->lockForUpdate()
                ->findOrFail($rental->id);

            if (! in_array($locked->status, ['active', 'partial_return', 'correction_pending'], true)) {
                throw new ConflictHttpException(
                    'Rental ini sudah selesai atau tidak dapat menerima pengembalian.',
                );
            }

            $correction = $locked->status === 'correction_pending'
                ? $this->corrections->openSession($locked)
                : null;

            if ($locked->status === 'correction_pending' && $correction === null) {
                throw new ConflictHttpException('Sesi koreksi operasional tidak ditemukan.');
            }

            $rawItemInputs = $data['items'] ?? [];
            /** @var list<array<string, mixed>> $itemInputs */
            $itemInputs = [];
            if (is_array($rawItemInputs)) {
                foreach ($rawItemInputs as $input) {
                    if (is_array($input)) {
                        $itemInputs[] = $input;
                    }
                }
            }
            /** @var array<int, array<string, mixed>> $indexedItemInputs */
            $indexedItemInputs = [];
            foreach ($itemInputs as $input) {
                $indexedItemInputs[(int) ($input['rental_item_asset_id'] ?? 0)] = $input;
            }
            /** @var Collection<int, array<string, mixed>> $inputItems */
            $inputItems = collect($indexedItemInputs);

            if ($correction !== null) {
                $this->swapCorrectedAssets($locked, $correction, $inputItems, $actor);
            }

            $units = RentalItemAsset::query()
                ->whereIn('id', $inputItems->keys())
                ->whereHas('rentalItem', fn ($query) => $query->where('rental_id', $locked->id))
                ->where('status', 'out')
                ->with(['asset', 'rentalItem'])
                ->lockForUpdate()
                ->get();

            if ($units->count() !== $inputItems->count()) {
                throw new ConflictHttpException(
                    'Ada unit yang tidak termasuk rental ini atau sudah dikembalikan.',
                );
            }

            if ($correction !== null) {
                $snapshot = $this->correctionSnapshot($correction);
                $rawSnapshotUnits = is_array($snapshot)
                    ? ($snapshot['units'] ?? [])
                    : [];
                /** @var list<array<string, mixed>> $snapshotUnits */
                $snapshotUnits = [];
                if (is_array($rawSnapshotUnits)) {
                    foreach ($rawSnapshotUnits as $unit) {
                        if (is_array($unit)) {
                            $snapshotUnits[] = $unit;
                        }
                    }
                }
                $expectedAssignmentIds = collect($snapshotUnits)
                    ->pluck('rental_item_asset_id')->sort()->values()->all();
                $submittedAssignmentIds = $units->pluck('id')->sort()->values()->all();

                if ($expectedAssignmentIds !== $submittedAssignmentIds) {
                    throw ValidationException::withMessages([
                        'items' => 'Semua unit dari return yang dibuka harus difinalisasi bersama.',
                    ]);
                }
            }

            $returnedAt = $data['returned_at'];
            $lateFee = $inputItems->sum(fn (array $item): float => (float) ($item['late_fee_amount'] ?? 0));
            $damageFee = $inputItems->sum(fn (array $item): float => (float) ($item['damage_fee_amount'] ?? 0));
            $cleaningFee = $inputItems->sum(fn (array $item): float => (float) ($item['cleaning_fee_amount'] ?? 0));
            $discount = (float) ($data['discount_amount'] ?? 0);
            $grossCharge = $lateFee + $damageFee + $cleaningFee;
            $isFinalReturn = $correction !== null
                || $this->willComplete($locked, $units->count());

            if ($correction !== null && (
                $grossCharge > 0
                || $discount > 0
                || (float) ($data['payment_amount'] ?? 0) > 0
            )) {
                throw ValidationException::withMessages([
                    'items' => 'Koreksi operasional tidak menerima perubahan nominal. Gunakan koreksi keuangan.',
                ]);
            }

            if ($discount > $grossCharge) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Diskon biaya tidak boleh melebihi total biaya pengembalian.',
                ]);
            }

            $payment = (float) ($data['payment_amount'] ?? 0);
            $balanceAfterReturn = (float) $locked->balance_due
                + $grossCharge
                - $discount
                - $payment;

            if ($payment > (float) $locked->balance_due + $grossCharge - $discount) {
                throw ValidationException::withMessages([
                    'payment_amount' => 'Pembayaran melebihi sisa tagihan setelah biaya pengembalian.',
                ]);
            }

            if ($isFinalReturn && $balanceAfterReturn > 0.009) {
                throw ValidationException::withMessages([
                    'payment_amount' => sprintf(
                        'Rental belum lunas. Sisa tagihan Rp%s harus dibayar sebelum pengembalian diselesaikan.',
                        number_format($balanceAfterReturn, 0, ',', '.'),
                    ),
                ]);
            }

            $return = RentalReturn::query()->create([
                'branch_id' => $locked->branch_id,
                'rental_id' => $locked->id,
                'received_by_employee_id' => $actor->employee?->id,
                'return_number' => $this->numbers->nextReturn($locked->branch),
                'type' => $isFinalReturn ? 'final' : 'partial',
                'status' => 'completed',
                'returned_at' => $returnedAt,
                'late_fee_amount' => $lateFee,
                'damage_fee_amount' => $damageFee,
                'cleaning_fee_amount' => $cleaningFee,
                'discount_amount' => $discount,
                'total_charge_amount' => $grossCharge - $discount,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            foreach ($units as $unit) {
                $input = $inputItems->get($unit->id);
                if ($input === null) {
                    throw new ConflictHttpException(
                        'Data pengembalian unit tidak ditemukan.',
                    );
                }
                $condition = $input['condition'];
                $returnItem = $return->items()->create([
                    'rental_item_id' => $unit->rental_item_id,
                    'asset_id' => $unit->asset_id,
                    'quantity' => 1,
                    'condition' => $condition,
                    'status' => $condition === 'lost' ? 'lost' : 'returned',
                    'late_fee_amount' => $input['late_fee_amount'] ?? 0,
                    'damage_fee_amount' => $input['damage_fee_amount'] ?? 0,
                    'cleaning_fee_amount' => $input['cleaning_fee_amount'] ?? 0,
                    'notes' => $input['notes'] ?? null,
                ]);

                $unit->update([
                    'return_condition' => $condition,
                    'returned_at' => $returnedAt,
                    'status' => $condition === 'lost' ? 'lost' : 'returned',
                    'notes' => $input['notes'] ?? $unit->notes,
                ]);
                $previousStatus = $unit->asset->status;
                $previousCondition = $unit->asset->condition;
                $assetStatus = match ($condition) {
                    'damaged' => 'maintenance',
                    'lost' => 'lost',
                    default => 'available',
                };
                $unit->asset->update([
                    'status' => $assetStatus,
                    'condition' => $condition,
                ]);
                $this->syncMaintenanceInventory(
                    $unit->asset->current_branch_id,
                    $unit->asset->product_id,
                );
                if ($condition === 'damaged') {
                    $this->createMaintenanceOrder(
                        $unit->asset,
                        $return,
                        $input['notes'] ?? null,
                        $actor,
                    );
                }
                DB::table('asset_status_histories')->insert([
                    'asset_id' => $unit->asset_id,
                    'branch_id' => $locked->branch_id,
                    'from_status' => $previousStatus,
                    'to_status' => $assetStatus,
                    'from_condition' => $previousCondition,
                    'to_condition' => $condition,
                    'source_type' => RentalReturn::class,
                    'source_id' => $return->id,
                    'reason' => "Pengembalian {$return->return_number}.",
                    'changed_by' => $actor->id,
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                AssetInspection::query()->create([
                    'branch_id' => $locked->branch_id,
                    'asset_id' => $unit->asset_id,
                    'rental_item_id' => $unit->rental_item_id,
                    'rental_return_item_id' => $returnItem->id,
                    'type' => 'return',
                    'condition' => $condition,
                    'notes' => $input['notes'] ?? null,
                    'inspected_by' => $actor->id,
                    'inspected_at' => $returnedAt,
                ]);

                if ((float) ($input['damage_fee_amount'] ?? 0) > 0) {
                    $returnItem->damageCharges()->create([
                        'asset_id' => $unit->asset_id,
                        'type' => $condition === 'lost' ? 'loss' : 'damage',
                        'description' => $input['notes']
                            ?? "Biaya kondisi {$condition} saat pengembalian.",
                        'amount' => $input['damage_fee_amount'],
                        'status' => 'charged',
                        'decided_by' => $actor->id,
                        'decided_at' => now(),
                    ]);
                }
            }

            $this->refreshItemStatuses($locked);
            if ($correction === null) {
                $this->applyFinancials($locked, $return, $data, $actor);
            }
            $this->refreshRentalStatus($locked, $return, $actor);

            if ($correction !== null) {
                $this->corrections->finalize(
                    $correction,
                    $return,
                    $locked,
                    $actor,
                );

                // RentalOperationalCorrectionManager owns the correction
                // ledger. The locked return aggregate owns the final rental
                // state, so close correction_pending here in the same
                // transaction after the correction has been finalized.
                $locked->forceFill([
                    'status' => 'returned',
                    'returned_at' => $return->returned_at,
                    'updated_by' => $actor->id,
                ])->saveOrFail();
            }

            return $return->fresh(['items.asset', 'rental']);
        }, 3);
    }

    private function correctionSnapshot(RentalOperationalCorrection $correction): mixed
    {
        return $correction->getAttribute('snapshot_before');
    }

    private function syncMaintenanceInventory(int $branchId, int $productId): void
    {
        $quantity = DB::table('assets')
            ->where('current_branch_id', $branchId)
            ->where('product_id', $productId)
            ->whereNull('deleted_at')
            ->where('status', 'maintenance')
            ->count();

        DB::table('branch_inventories')->updateOrInsert(
            ['branch_id' => $branchId, 'product_id' => $productId],
            [
                'quantity_maintenance' => $quantity,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /** @param Collection<int, array<string, mixed>> $assignments */
    private function swapCorrectedAssets(
        Rental $rental,
        RentalOperationalCorrection $correction,
        Collection $assignments,
        User $actor,
    ): void {
        foreach ($assignments as $assignmentId => $input) {
            $replacementId = (int) ($input['replacement_asset_id'] ?? 0);

            if ($replacementId === 0) {
                continue;
            }

            $assignment = RentalItemAsset::query()
                ->with(['asset', 'rentalItem'])
                ->lockForUpdate()
                ->findOrFail($assignmentId);

            if ($assignment->asset_id === $replacementId) {
                continue;
            }

            $replacement = Asset::query()->lockForUpdate()->findOrFail($replacementId);

            if (
                $assignment->rentalItem->rental_id !== $rental->id
                || $replacement->current_branch_id !== $rental->branch_id
                || $replacement->product_id !== $assignment->rentalItem->product_id
                || $replacement->status !== 'available'
                || ! $replacement->is_active
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Unit pengganti tidak tersedia pada produk dan cabang yang sama.',
                ]);
            }

            $original = $assignment->asset;
            $originalStatus = $original->status;
            $originalCondition = $original->condition;
            $replacementStatus = $replacement->status;
            $replacementCondition = $replacement->condition;
            $original->update([
                'status' => 'available',
                'condition' => $assignment->checkout_condition,
            ]);
            $replacement->update(['status' => 'rented']);
            $assignment->update([
                'asset_id' => $replacement->id,
                'checkout_condition' => $replacementCondition,
            ]);

            foreach (
                [
                    [$original, $originalStatus, $originalCondition, 'available'],
                    [$replacement, $replacementStatus, $replacementCondition, 'rented'],
                ] as [$asset, $fromStatus, $fromCondition, $toStatus]
            ) {
                DB::table('asset_status_histories')->insert([
                    'asset_id' => $asset->id,
                    'branch_id' => $rental->branch_id,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                    'from_condition' => $fromCondition,
                    'to_condition' => $asset->condition,
                    'source_type' => RentalOperationalCorrection::class,
                    'source_id' => $correction->id,
                    'reason' => "Koreksi unit melalui {$correction->correction_number}.",
                    'changed_by' => $actor->id,
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function createMaintenanceOrder(
        Asset $asset,
        RentalReturn $return,
        ?string $notes,
        User $actor,
    ): void {
        $hasActiveOrder = MaintenanceOrder::query()
            ->where('asset_id', $asset->id)
            ->whereIn('status', ['reported', 'in_progress'])
            ->exists();

        if ($hasActiveOrder) {
            return;
        }

        DB::table('branches')->where('id', $asset->current_branch_id)->lockForUpdate()->first();
        $branchCode = DB::table('branches')
            ->where('id', $asset->current_branch_id)
            ->value('code');
        $prefix = 'MNT-'.$branchCode.'-'.now()->format('ymd').'-';
        $last = MaintenanceOrder::query()
            ->where('branch_id', $asset->current_branch_id)
            ->where('maintenance_number', 'like', $prefix.'%')
            ->orderByDesc('maintenance_number')
            ->value('maintenance_number');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        MaintenanceOrder::query()->create([
            'branch_id' => $asset->current_branch_id,
            'asset_id' => $asset->id,
            'maintenance_number' => $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'type' => 'repair',
            'status' => 'reported',
            'problem_description' => $notes
                ?? "Kerusakan tercatat saat {$return->return_number}.",
            'reported_at' => $return->returned_at,
            'created_by' => $actor->id,
        ]);
    }

    private function willComplete(Rental $rental, int $returningCount): bool
    {
        $remaining = RentalItemAsset::query()
            ->whereHas('rentalItem', fn ($query) => $query->where('rental_id', $rental->id))
            ->where('status', 'out')
            ->count();

        return $returningCount === $remaining;
    }

    private function refreshItemStatuses(Rental $rental): void
    {
        RentalItem::query()->where('rental_id', $rental->id)->get()
            ->each(function (RentalItem $item): void {
                $returned = $item->assets()
                    ->whereIn('status', ['returned', 'lost'])
                    ->count();
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

    /** @param array<string, mixed> $data */
    private function applyFinancials(
        Rental $rental,
        RentalReturn $return,
        array $data,
        User $actor,
    ): void {
        $charge = (float) $return->total_charge_amount;
        $payment = (float) ($data['payment_amount'] ?? 0);
        $newBalance = (float) $rental->balance_due + $charge;

        if ($payment > $newBalance) {
            throw ValidationException::withMessages([
                'payment_amount' => 'Pembayaran melebihi sisa tagihan setelah biaya pengembalian.',
            ]);
        }

        if ($payment > 0) {
            $this->payments->record($rental->branch, [
                'customer_id' => $rental->customer_id,
                'rental_id' => $rental->id,
                'payment_method_id' => $data['payment_method_id'],
                'financial_category_code' => 'RENTAL',
                'cash_session_id' => $data['cash_session_id'] ?? null,
                'direction' => 'in',
                'type' => 'rental',
                'source_context' => 'rental_return',
                'amount' => $payment,
                'paid_at' => $return->returned_at,
                'external_reference' => $data['payment_reference'] ?? null,
                'notes' => "Pembayaran saat pengembalian {$return->return_number}.",
            ], $actor);
        }

        $rental->update([
            'late_fee_amount' => (float) $rental->late_fee_amount
                + (float) $return->late_fee_amount,
            'damage_fee_amount' => (float) $rental->damage_fee_amount
                + (float) $return->damage_fee_amount
                + (float) $return->cleaning_fee_amount
                - (float) $return->discount_amount,
            'paid_amount' => (float) $rental->paid_amount + $payment,
            'balance_due' => $newBalance - $payment,
            'updated_by' => $actor->id,
        ]);
    }

    private function refreshRentalStatus(
        Rental $rental,
        RentalReturn $return,
        User $actor,
    ): void {
        $remaining = RentalItemAsset::query()
            ->whereHas('rentalItem', fn ($query) => $query->where('rental_id', $rental->id))
            ->where('status', 'out')
            ->exists();
        $from = $rental->status;
        $to = $remaining ? 'partial_return' : 'returned';
        $rental->update([
            'status' => $to,
            'returned_at' => $remaining ? null : $return->returned_at,
        ]);
        $rental->statusHistories()->create([
            'from_status' => $from,
            'to_status' => $to,
            'reason' => "Pengembalian {$return->return_number} diproses.",
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
    }
}
