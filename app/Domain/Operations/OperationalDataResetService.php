<?php

namespace App\Domain\Operations;

use App\Models\Branch;
use App\Models\BranchTransfer;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OperationalDataResetService
{
    /**
     * @return array<string, int>
     */
    public function preview(int $companyId, ?Branch $branch): array
    {
        $branchIds = $this->branchIds($companyId, $branch);
        $transferIds = $this->transferIds($companyId, $branchIds);
        $rentalIds = $this->ids('rentals', 'id', static fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        $bookingIds = $this->ids('bookings', 'id', static fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        $rentalItemIds = $this->ids('rental_items', 'id', static fn (Builder $query) => $query->whereIn('rental_id', $rentalIds));
        $returnIds = $this->ids('rental_returns', 'id', static fn (Builder $query) => $query->whereIn('rental_id', $rentalIds));
        $returnItemIds = $this->ids('rental_return_items', 'id', static fn (Builder $query) => $query->whereIn('rental_return_id', $returnIds));
        $transferItemIds = $this->ids('branch_transfer_items', 'id', static fn (Builder $query) => $query->whereIn('branch_transfer_id', $transferIds));
        $transferPaymentIds = $this->ids(
            'branch_transfer_expenses',
            'payment_id',
            static fn (Builder $query) => $query
                ->whereIn('branch_transfer_id', $transferIds)
                ->whereNotNull('payment_id'),
        );
        $paymentIds = $this->paymentIds($bookingIds, $rentalIds, $transferPaymentIds);
        $inspectionIds = $this->inspectionIds($branchIds, $rentalItemIds, $returnItemIds, $transferItemIds);

        return [
            'bookings' => count($bookingIds),
            'reservations' => DB::table('asset_reservations')->whereIn('booking_id', $bookingIds)->count(),
            'rentals' => count($rentalIds),
            'returns' => count($returnIds),
            'payments' => count($paymentIds),
            'refunds' => DB::table('refunds')
                ->where(function (Builder $query) use ($bookingIds, $rentalIds, $paymentIds): void {
                    $query->whereIn('booking_id', $bookingIds)
                        ->orWhereIn('rental_id', $rentalIds)
                        ->orWhereIn('payment_id', $paymentIds);
                })
                ->count(),
            'financial_adjustments' => DB::table('rental_financial_adjustments')
                ->whereIn('rental_id', $rentalIds)
                ->count(),
            'maintenance' => DB::table('maintenance_orders')->whereIn('branch_id', $branchIds)->count(),
            'transfers' => count($transferIds),
            'transfer_expenses' => DB::table('branch_transfer_expenses')
                ->whereIn('branch_transfer_id', $transferIds)
                ->count(),
            'inspections' => count($inspectionIds),
            'serialized_assets' => DB::table('assets')
                ->whereIn('current_branch_id', $branchIds)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->count(),
            'bulk_inventory_rows' => DB::table('branch_inventories')->whereIn('branch_id', $branchIds)->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function reset(int $companyId, ?Branch $branch, bool $normalizeCondition): array
    {
        $branchIds = $this->branchIds($companyId, $branch);
        $transferIds = $this->transferIds($companyId, $branchIds);
        $bookingIds = $this->ids('bookings', 'id', static fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        $rentalIds = $this->ids('rentals', 'id', static fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        $rentalItemIds = $this->ids('rental_items', 'id', static fn (Builder $query) => $query->whereIn('rental_id', $rentalIds));
        $returnIds = $this->ids('rental_returns', 'id', static fn (Builder $query) => $query->whereIn('rental_id', $rentalIds));
        $returnItemIds = $this->ids('rental_return_items', 'id', static fn (Builder $query) => $query->whereIn('rental_return_id', $returnIds));
        $transferItemIds = $this->ids('branch_transfer_items', 'id', static fn (Builder $query) => $query->whereIn('branch_transfer_id', $transferIds));
        $transferPaymentIds = $this->ids(
            'branch_transfer_expenses',
            'payment_id',
            static fn (Builder $query) => $query
                ->whereIn('branch_transfer_id', $transferIds)
                ->whereNotNull('payment_id'),
        );
        $paymentIds = $this->paymentIds($bookingIds, $rentalIds, $transferPaymentIds);
        $refundIds = $this->refundIds($bookingIds, $rentalIds, $paymentIds);
        $maintenanceIds = $this->ids('maintenance_orders', 'id', static fn (Builder $query) => $query->whereIn('branch_id', $branchIds));
        $inspectionIds = $this->inspectionIds($branchIds, $rentalItemIds, $returnItemIds, $transferItemIds);
        $filePaths = $this->filePaths(
            $transferIds,
            $inspectionIds,
            $rentalIds,
            $paymentIds,
            $refundIds,
        );
        $preview = $this->preview($companyId, $branch);

        DB::transaction(function () use (
            $branchIds,
            $transferIds,
            $bookingIds,
            $rentalIds,
            $paymentIds,
            $refundIds,
            $maintenanceIds,
            $inspectionIds,
            $normalizeCondition,
        ): void {
            DB::table('status_histories')
                ->where('subject_type', BranchTransfer::class)
                ->whereIn('subject_id', $transferIds)
                ->delete();

            DB::table('asset_status_histories')
                ->whereIn('branch_id', $branchIds)
                ->delete();

            DB::table('asset_inspection_media')
                ->whereIn('asset_inspection_id', $inspectionIds)
                ->delete();
            DB::table('asset_inspections')->whereIn('id', $inspectionIds)->delete();

            DB::table('rental_operational_corrections')
                ->whereIn('rental_id', $rentalIds)
                ->delete();
            DB::table('rental_financial_adjustments')
                ->whereIn('rental_id', $rentalIds)
                ->delete();

            DB::table('cash_transactions')
                ->where(function (Builder $query) use ($paymentIds, $refundIds): void {
                    $query->whereIn('payment_id', $paymentIds)
                        ->orWhereIn('refund_id', $refundIds);
                })
                ->delete();
            DB::table('refunds')->whereIn('id', $refundIds)->delete();
            DB::table('payments')->whereIn('id', $paymentIds)->delete();

            DB::table('maintenance_orders')->whereIn('id', $maintenanceIds)->delete();
            DB::table('rentals')->whereIn('id', $rentalIds)->delete();
            DB::table('bookings')->whereIn('id', $bookingIds)->delete();
            DB::table('branch_transfers')->whereIn('id', $transferIds)->delete();

            $assetUpdates = [
                'status' => 'available',
                'updated_at' => now(),
            ];
            if ($normalizeCondition) {
                $assetUpdates['condition'] = 'good';
            }

            DB::table('assets')
                ->whereIn('current_branch_id', $branchIds)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->update($assetUpdates);

            DB::table('branch_inventories')
                ->whereIn('branch_id', $branchIds)
                ->update([
                    'quantity_reserved' => 0,
                    'quantity_rented' => 0,
                    'quantity_maintenance' => 0,
                    'quantity_in_transfer' => 0,
                    'updated_at' => now(),
                ]);
        }, 3);

        if ($filePaths !== []) {
            Storage::disk('local')->delete($filePaths);
        }

        return $preview;
    }

    /** @return list<int> */
    private function branchIds(int $companyId, ?Branch $branch): array
    {
        if ($branch !== null) {
            return [(int) $branch->id];
        }

        $ids = DB::table('branches')
            ->where('company_id', $companyId)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return array_values($ids);
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<int>
     */
    private function transferIds(int $companyId, array $branchIds): array
    {
        $ids = DB::table('branch_transfers')
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($branchIds): void {
                $query->whereIn('from_branch_id', $branchIds)
                    ->orWhereIn('to_branch_id', $branchIds);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return array_values($ids);
    }

    /**
     * @param  list<int>  $bookingIds
     * @param  list<int>  $rentalIds
     * @param  list<int>  $transferPaymentIds
     * @return list<int>
     */
    private function paymentIds(array $bookingIds, array $rentalIds, array $transferPaymentIds): array
    {
        $ids = DB::table('payments')
            ->where(function (Builder $query) use ($bookingIds, $rentalIds, $transferPaymentIds): void {
                $query->whereIn('booking_id', $bookingIds)
                    ->orWhereIn('rental_id', $rentalIds)
                    ->orWhereIn('id', $transferPaymentIds);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return array_values($ids);
    }

    /**
     * @param  list<int>  $bookingIds
     * @param  list<int>  $rentalIds
     * @param  list<int>  $paymentIds
     * @return list<int>
     */
    private function refundIds(array $bookingIds, array $rentalIds, array $paymentIds): array
    {
        $ids = DB::table('refunds')
            ->where(function (Builder $query) use ($bookingIds, $rentalIds, $paymentIds): void {
                $query->whereIn('booking_id', $bookingIds)
                    ->orWhereIn('rental_id', $rentalIds)
                    ->orWhereIn('payment_id', $paymentIds);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return array_values($ids);
    }

    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $rentalItemIds
     * @param  list<int>  $returnItemIds
     * @param  list<int>  $transferItemIds
     * @return list<int>
     */
    private function inspectionIds(
        array $branchIds,
        array $rentalItemIds,
        array $returnItemIds,
        array $transferItemIds,
    ): array {
        $ids = DB::table('asset_inspections')
            ->where(function (Builder $query) use ($branchIds, $rentalItemIds, $returnItemIds, $transferItemIds): void {
                $query->whereIn('branch_id', $branchIds)
                    ->orWhereIn('rental_item_id', $rentalItemIds)
                    ->orWhereIn('rental_return_item_id', $returnItemIds)
                    ->orWhereIn('branch_transfer_item_id', $transferItemIds);
            })
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return array_values($ids);
    }

    /**
     * @param  list<int>  $transferIds
     * @param  list<int>  $inspectionIds
     * @param  list<int>  $rentalIds
     * @param  list<int>  $paymentIds
     * @param  list<int>  $refundIds
     * @return list<string>
     */
    private function filePaths(
        array $transferIds,
        array $inspectionIds,
        array $rentalIds,
        array $paymentIds,
        array $refundIds,
    ): array {
        $paths = collect()
            ->merge(DB::table('branch_transfer_documents')->whereIn('branch_transfer_id', $transferIds)->pluck('path'))
            ->merge(DB::table('asset_inspection_media')->whereIn('asset_inspection_id', $inspectionIds)->pluck('path'))
            ->merge(DB::table('rental_collaterals')->whereIn('rental_id', $rentalIds)->pluck('document_path'))
            ->merge(DB::table('payments')->whereIn('id', $paymentIds)->pluck('proof_path'))
            ->merge(DB::table('refunds')->whereIn('id', $refundIds)->pluck('proof_path'))
            ->filter(static fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(static fn (mixed $path): string => (string) $path)
            ->unique()
            ->values()
            ->all();

        return array_values($paths);
    }

    /**
     * @param  callable(Builder): mixed  $scope
     * @return list<int>
     */
    private function ids(string $table, string $column, callable $scope): array
    {
        $query = DB::table($table);
        $scope($query);

        $ids = $query
            ->pluck($column)
            ->filter(static fn (mixed $id): bool => $id !== null)
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        return array_values($ids);
    }
}
