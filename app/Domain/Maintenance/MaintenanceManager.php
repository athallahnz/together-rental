<?php

namespace App\Domain\Maintenance;

use App\Models\Asset;
use App\Models\BranchInventory;
use App\Models\MaintenanceOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MaintenanceManager
{
    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): MaintenanceOrder
    {
        return DB::transaction(function () use ($data, $actor): MaintenanceOrder {
            $asset = Asset::query()->lockForUpdate()->findOrFail($data['asset_id']);
            $this->guardBranch($asset, $actor);

            if (in_array($asset->status, ['rented', 'reserved', 'in_transit', 'lost', 'retired'], true)) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Status aset tidak memungkinkan maintenance baru.',
                ]);
            }

            if (MaintenanceOrder::query()->where('asset_id', $asset->id)
                ->whereIn('status', ['reported', 'in_progress'])->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aset masih memiliki maintenance aktif.',
                ]);
            }

            $previousStatus = $asset->status;
            $previousCondition = $asset->condition;
            $asset->forceFill(['status' => 'maintenance'])->save();
            $order = MaintenanceOrder::query()->create([
                ...$data,
                'branch_id' => $asset->current_branch_id,
                'maintenance_number' => $this->nextNumber($asset),
                'status' => 'reported',
                'reported_at' => now(),
                'created_by' => $actor->id,
            ]);
            $this->recordAssetHistory(
                $asset,
                $order,
                $previousStatus,
                $previousCondition,
                'Maintenance dilaporkan.',
                $actor,
            );
            $this->syncInventory($asset);

            return $order;
        });
    }

    public function start(MaintenanceOrder $maintenance, User $actor): MaintenanceOrder
    {
        return DB::transaction(function () use ($maintenance, $actor): MaintenanceOrder {
            $locked = MaintenanceOrder::query()->lockForUpdate()->findOrFail($maintenance->id);
            $asset = Asset::query()->lockForUpdate()->findOrFail($locked->asset_id);
            $this->guardBranch($asset, $actor);

            if ($locked->status !== 'reported') {
                throw ValidationException::withMessages([
                    'maintenance' => 'Hanya maintenance berstatus dilaporkan yang dapat dimulai.',
                ]);
            }

            $locked->update(['status' => 'in_progress', 'started_at' => now()]);

            return $locked;
        });
    }

    /** @param array<string, mixed> $data */
    public function complete(
        MaintenanceOrder $maintenance,
        array $data,
        User $actor,
    ): MaintenanceOrder {
        return DB::transaction(function () use ($maintenance, $data, $actor): MaintenanceOrder {
            $locked = MaintenanceOrder::query()->lockForUpdate()->findOrFail($maintenance->id);
            $asset = Asset::query()->lockForUpdate()->findOrFail($locked->asset_id);
            $this->guardBranch($asset, $actor);

            if (! in_array($locked->status, ['reported', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Maintenance ini sudah selesai atau dibatalkan.',
                ]);
            }

            $previousStatus = $asset->status;
            $previousCondition = $asset->condition;
            $asset->update([
                'status' => $data['asset_disposition'],
                'condition' => $data['asset_condition'],
            ]);
            $locked->update([
                'status' => 'completed',
                'resolution' => $data['resolution'],
                'actual_cost' => $data['actual_cost'],
                'started_at' => $locked->started_at ?? now(),
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);
            $this->recordAssetHistory(
                $asset,
                $locked,
                $previousStatus,
                $previousCondition,
                'Maintenance selesai: '.$data['resolution'],
                $actor,
            );
            $this->syncInventory($asset);

            return $locked;
        });
    }

    public function cancel(
        MaintenanceOrder $maintenance,
        string $reason,
        User $actor,
    ): MaintenanceOrder {
        return DB::transaction(function () use ($maintenance, $reason, $actor): MaintenanceOrder {
            $locked = MaintenanceOrder::query()->lockForUpdate()->findOrFail($maintenance->id);
            $asset = Asset::query()->lockForUpdate()->findOrFail($locked->asset_id);
            $this->guardBranch($asset, $actor);

            if (! in_array($locked->status, ['reported', 'in_progress'], true)) {
                throw ValidationException::withMessages([
                    'maintenance' => 'Maintenance ini sudah selesai atau dibatalkan.',
                ]);
            }

            $previousStatus = $asset->status;
            $asset->update([
                'status' => $asset->condition === 'good' ? 'available' : 'maintenance',
            ]);
            $locked->update([
                'status' => 'cancelled',
                'resolution' => 'Dibatalkan: '.$reason,
            ]);
            $this->recordAssetHistory(
                $asset,
                $locked,
                $previousStatus,
                $asset->condition,
                'Maintenance dibatalkan: '.$reason,
                $actor,
            );
            $this->syncInventory($asset);

            return $locked;
        });
    }

    private function guardBranch(Asset $asset, User $actor): void
    {
        if (! $actor->accessibleBranches()->whereKey($asset->current_branch_id)->exists()) {
            abort(404);
        }
    }

    private function nextNumber(Asset $asset): string
    {
        $branchCode = DB::table('branches')->where('id', $asset->current_branch_id)->value('code');
        $prefix = 'MNT-'.$branchCode.'-'.now()->format('ymd').'-';
        $last = MaintenanceOrder::query()
            ->where('branch_id', $asset->current_branch_id)
            ->where('maintenance_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('maintenance_number')
            ->value('maintenance_number');
        $sequence = $last === null ? 1 : ((int) substr($last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function syncInventory(Asset $asset): void
    {
        $maintenance = Asset::query()
            ->where('current_branch_id', $asset->current_branch_id)
            ->where('product_id', $asset->product_id)
            ->where('status', 'maintenance')
            ->count();
        BranchInventory::query()->updateOrCreate(
            ['branch_id' => $asset->current_branch_id, 'product_id' => $asset->product_id],
            ['quantity_maintenance' => $maintenance],
        );
    }

    private function recordAssetHistory(
        Asset $asset,
        MaintenanceOrder $order,
        string $fromStatus,
        string $fromCondition,
        string $reason,
        User $actor,
    ): void {
        DB::table('asset_status_histories')->insert([
            'asset_id' => $asset->id,
            'branch_id' => $asset->current_branch_id,
            'from_status' => $fromStatus,
            'to_status' => $asset->status,
            'from_condition' => $fromCondition,
            'to_condition' => $asset->condition,
            'source_type' => MaintenanceOrder::class,
            'source_id' => $order->id,
            'reason' => $reason,
            'changed_by' => $actor->id,
            'changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
