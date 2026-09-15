<?php

namespace App\Domain\Assets;

use App\Models\Asset;
use App\Models\AssetAcquisition;
use App\Models\AssetAcquisitionItem;
use App\Models\AssetDisposal;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetLifecycleManager
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function acquire(array $data, User $actor): AssetAcquisition
    {
        $branchId = (int) $data['branch_id'];
        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];
        $serialNumbers = $this->serialNumbers($data['serial_numbers'] ?? null);

        if (count($serialNumbers) > $quantity) {
            throw ValidationException::withMessages([
                'serial_numbers' => 'Jumlah serial number tidak boleh melebihi jumlah unit.',
            ]);
        }

        if (count($serialNumbers) !== count(array_unique($serialNumbers))) {
            throw ValidationException::withMessages([
                'serial_numbers' => 'Serial number dalam satu acquisition tidak boleh duplikat.',
            ]);
        }

        $this->guardBranch($actor, $branchId);
        $product = Product::query()
            ->where('company_id', $actor->company_id)
            ->where('tracking_type', 'serialized')
            ->where('is_active', true)
            ->findOrFail($productId);

        $acquisitionDate = CarbonImmutable::parse((string) $data['acquisition_date']);
        $unitCost = (float) $data['unit_cost'];
        $replacementValue = array_key_exists('replacement_value', $data) && $data['replacement_value'] !== null
            ? (float) $data['replacement_value']
            : (float) $product->replacement_value;

        return DB::transaction(function () use (
            $actor,
            $branchId,
            $product,
            $quantity,
            $serialNumbers,
            $acquisitionDate,
            $unitCost,
            $replacementValue,
            $data,
        ): AssetAcquisition {
            $branch = Branch::query()->lockForUpdate()->findOrFail($branchId);
            $number = $this->nextAcquisitionNumber($branch, $acquisitionDate);
            $acquisition = AssetAcquisition::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $branch->id,
                'acquisition_number' => $number,
                'acquisition_date' => $acquisitionDate->toDateString(),
                'vendor_name' => $this->nullableString($data['vendor_name'] ?? null),
                'reference_number' => $this->nullableString($data['reference_number'] ?? null),
                'total_amount' => $unitCost * $quantity,
                'notes' => $this->nullableString($data['notes'] ?? null),
                'created_by' => $actor->id,
            ]);

            for ($index = 1; $index <= $quantity; $index++) {
                $serialNumber = $serialNumbers[$index - 1] ?? null;
                $asset = Asset::query()->create([
                    'product_id' => $product->id,
                    'owning_branch_id' => $branch->id,
                    'current_branch_id' => $branch->id,
                    'asset_code' => $this->assetCode($number, $index),
                    'serial_number' => $serialNumber,
                    'status' => 'available',
                    'condition' => 'good',
                    'purchase_date' => $acquisitionDate->toDateString(),
                    'purchase_price' => $unitCost,
                    'replacement_value' => $replacementValue,
                    'warranty_until' => $data['warranty_until'] ?? null,
                    'notes' => null,
                    'is_active' => true,
                ]);

                AssetAcquisitionItem::query()->create([
                    'asset_acquisition_id' => $acquisition->id,
                    'asset_id' => $asset->id,
                    'product_id' => $product->id,
                    'purchase_price' => $unitCost,
                    'replacement_value' => $replacementValue,
                    'serial_number' => $serialNumber,
                    'warranty_until' => $data['warranty_until'] ?? null,
                ]);

                $this->recordHistory(
                    asset: $asset,
                    source: $acquisition,
                    fromStatus: null,
                    toStatus: 'available',
                    reason: 'Perolehan aset '.$number,
                    actor: $actor,
                );
            }

            $this->syncSerializedInventory($product->id, $branch->id);

            return $acquisition->load(['branch:id,code,name', 'items.asset:id,asset_code,serial_number']);
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function dispose(Asset $asset, array $data, User $actor): AssetDisposal
    {
        return DB::transaction(function () use ($asset, $data, $actor): AssetDisposal {
            $locked = Asset::query()
                ->with('product:id,company_id,tracking_type,name')
                ->lockForUpdate()
                ->findOrFail($asset->id);

            $product = $locked->product;
            abort_unless($product instanceof Product, 404);
            abort_unless((int) $product->company_id === (int) $actor->company_id, 404);
            $this->guardBranch($actor, (int) $locked->current_branch_id);

            if (! $locked->is_active || $locked->status === 'retired') {
                throw ValidationException::withMessages([
                    'asset' => 'Aset ini sudah tidak aktif atau sudah retired.',
                ]);
            }

            if ($product->tracking_type !== 'serialized') {
                throw ValidationException::withMessages([
                    'asset' => 'Disposal ini hanya berlaku untuk aset serialized.',
                ]);
            }

            if (! in_array($locked->status, ['available', 'lost'], true)) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset harus berstatus tersedia atau hilang sebelum dapat di-dispose.',
                ]);
            }

            $method = (string) $data['method'];
            if ($locked->status === 'lost' && $method !== 'write_off') {
                throw ValidationException::withMessages([
                    'method' => 'Aset hilang hanya dapat ditutup dengan write-off.',
                ]);
            }

            $saleAmount = (float) ($data['sale_amount'] ?? 0);
            if ($method === 'sold' && $saleAmount <= 0) {
                throw ValidationException::withMessages([
                    'sale_amount' => 'Nilai penjualan wajib lebih dari nol untuk disposal dengan metode dijual.',
                ]);
            }
            if ($method !== 'sold') {
                $saleAmount = 0;
            }

            $disposalDate = CarbonImmutable::parse((string) $data['disposal_date']);
            if ($locked->purchase_date !== null && $disposalDate->isBefore($locked->purchase_date)) {
                throw ValidationException::withMessages([
                    'disposal_date' => 'Tanggal disposal tidak boleh sebelum tanggal perolehan aset.',
                ]);
            }

            if ($locked->reservations()
                ->where('status', 'reserved')
                ->where('ends_at', '>=', now())
                ->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset masih memiliki reservasi aktif atau mendatang.',
                ]);
            }

            if (DB::table('rental_item_assets')
                ->where('asset_id', $locked->id)
                ->whereNull('returned_at')
                ->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Aset masih terikat pada rental yang belum dikembalikan.',
                ]);
            }

            if ($locked->maintenanceOrders()->whereIn('status', ['reported', 'in_progress'])->exists()) {
                throw ValidationException::withMessages([
                    'asset' => 'Selesaikan maintenance aktif sebelum disposal.',
                ]);
            }

            $branch = Branch::query()->lockForUpdate()->findOrFail($locked->current_branch_id);
            $number = $this->nextDisposalNumber($branch, $disposalDate);
            $fromStatus = (string) $locked->status;
            $disposal = AssetDisposal::query()->create([
                'company_id' => $actor->company_id,
                'branch_id' => $branch->id,
                'asset_id' => $locked->id,
                'disposal_number' => $number,
                'disposal_date' => $disposalDate->toDateString(),
                'method' => $method,
                'sale_amount' => $saleAmount,
                'reason' => trim((string) $data['reason']),
                'notes' => $this->nullableString($data['notes'] ?? null),
                'disposed_by' => $actor->id,
            ]);

            $locked->forceFill([
                'status' => 'retired',
                'is_active' => false,
            ])->save();

            $this->recordHistory(
                asset: $locked,
                source: $disposal,
                fromStatus: $fromStatus,
                toStatus: 'retired',
                reason: strtoupper(str_replace('_', ' ', $method)).': '.trim((string) $data['reason']),
                actor: $actor,
            );
            $this->syncSerializedInventory($locked->product_id, $branch->id);

            return $disposal->load(['branch:id,code,name', 'asset.product:id,name,sku']);
        }, 3);
    }

    private function guardBranch(User $actor, int $branchId): void
    {
        abort_unless($actor->accessibleBranches()->whereKey($branchId)->exists(), 404);
    }

    /** @return list<string> */
    private function serialNumbers(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $serials = preg_split('/\r\n|\r|\n/', $value) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $serial): string => trim($serial),
            $serials,
        ), static fn (string $serial): bool => $serial !== ''));
    }

    private function nextAcquisitionNumber(Branch $branch, CarbonImmutable $date): string
    {
        return $this->nextNumber('ACQ', 'asset_acquisitions', 'acquisition_number', $branch, $date);
    }

    private function nextDisposalNumber(Branch $branch, CarbonImmutable $date): string
    {
        return $this->nextNumber('DSP', 'asset_disposals', 'disposal_number', $branch, $date);
    }

    private function nextNumber(
        string $kind,
        string $table,
        string $column,
        Branch $branch,
        CarbonImmutable $date,
    ): string {
        $prefix = $kind.'-'.$branch->code.'-'.$date->format('ymd').'-';
        $last = DB::table($table)
            ->where('branch_id', $branch->id)
            ->where($column, 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc($column)
            ->value($column);
        $sequence = $last === null ? 1 : ((int) substr((string) $last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function assetCode(string $acquisitionNumber, int $index): string
    {
        return str_replace('ACQ-', 'AST-', $acquisitionNumber)
            .'-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    }

    private function syncSerializedInventory(int $productId, int $branchId): void
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

    private function recordHistory(
        Asset $asset,
        AssetAcquisition|AssetDisposal $source,
        ?string $fromStatus,
        string $toStatus,
        string $reason,
        User $actor,
    ): void {
        DB::table('asset_status_histories')->insert([
            'asset_id' => $asset->id,
            'branch_id' => $asset->current_branch_id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_condition' => $fromStatus === null ? null : $asset->condition,
            'to_condition' => $asset->condition,
            'source_type' => $source::class,
            'source_id' => $source->id,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
