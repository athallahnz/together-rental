<?php

namespace App\Domain\Rentals;

use App\Models\Asset;
use App\Models\AssetInspection;
use App\Models\MaintenanceOrder;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\RentalItemAsset;
use App\Models\RentalReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RentalReturnManager
{
    public function __construct(private readonly RentalNumberGenerator $numbers) {}

    /** @param array<string, mixed> $data */
    public function process(Rental $rental, array $data, User $actor): RentalReturn
    {
        return DB::transaction(function () use ($rental, $data, $actor): RentalReturn {
            $locked = Rental::query()
                ->with('branch')
                ->lockForUpdate()
                ->findOrFail($rental->id);

            if (! in_array($locked->status, ['active', 'partial_return'], true)) {
                throw new ConflictHttpException(
                    'Rental ini sudah selesai atau tidak dapat menerima pengembalian.',
                );
            }

            $inputItems = collect($data['items'])->keyBy('rental_item_asset_id');
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

            $returnedAt = $data['returned_at'];
            $lateFee = $inputItems->sum(fn (array $item): float => (float) ($item['late_fee_amount'] ?? 0));
            $damageFee = $inputItems->sum(fn (array $item): float => (float) ($item['damage_fee_amount'] ?? 0));
            $cleaningFee = $inputItems->sum(fn (array $item): float => (float) ($item['cleaning_fee_amount'] ?? 0));
            $discount = (float) ($data['discount_amount'] ?? 0);
            $grossCharge = $lateFee + $damageFee + $cleaningFee;
            $isFinalReturn = $this->willComplete($locked, $units->count());

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
            $this->applyFinancials($locked, $return, $data, $actor);
            $this->refreshRentalStatus($locked, $return, $actor);

            return $return->fresh(['items.asset', 'rental']);
        }, 3);
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
            $categoryId = DB::table('financial_categories')
                ->where('company_id', $actor->company_id)
                ->where('code', 'RENTAL')
                ->value('id');
            Payment::query()->create([
                'branch_id' => $rental->branch_id,
                'customer_id' => $rental->customer_id,
                'rental_id' => $rental->id,
                'payment_method_id' => $data['payment_method_id'],
                'financial_category_id' => $categoryId,
                'payment_number' => $this->numbers->nextPayment($rental->branch),
                'direction' => 'in',
                'type' => 'rental',
                'status' => 'completed',
                'amount' => $payment,
                'paid_at' => $return->returned_at,
                'external_reference' => $data['payment_reference'] ?? null,
                'notes' => "Pembayaran saat pengembalian {$return->return_number}.",
                'received_by' => $actor->id,
            ]);
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
