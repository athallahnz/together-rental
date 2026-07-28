<?php

namespace App\Domain\Analytics;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class AssetAnalyticsService
{
    /**
     * @param  list<int>  $branchIds
     * @param  array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     branch_id: int|null,
     *     category_id: int|null,
     *     status: string,
     *     condition: string,
     *     search: string
     * }  $filters
     * @return array<string, mixed>
     */
    public function analyze(int $companyId, array $branchIds, array $filters): array
    {
        $scopedBranchIds = $filters['branch_id'] === null
            ? $branchIds
            : array_values(array_intersect($branchIds, [$filters['branch_id']]));

        $assets = $this->assets($companyId, $scopedBranchIds, $filters);

        if ($assets->isEmpty()) {
            return $this->emptyResult($filters['from'], $filters['to']);
        }

        /** @var list<int> $assetIds */
        $assetIds = $assets->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $metrics = $this->blankMetrics($assetIds);
        $quality = $this->blankQuality();
        $trend = $this->blankTrend($filters['from'], $filters['to']);

        $rentalRows = $this->rentalRows($assetIds);
        $extensionRows = $this->extensionRows($assetIds);
        $rentalItemCounts = $this->rentalItemAssetCounts($rentalRows, $extensionRows);
        $rentalLineTotals = $this->rentalLineTotals($rentalRows);
        $extensionLineTotals = $this->extensionLineTotals($extensionRows);

        $this->applyRentalRevenueAndIntervals(
            $rentalRows,
            $rentalItemCounts,
            $rentalLineTotals,
            $metrics,
            $quality,
            $trend,
            $filters['from'],
            $filters['to'],
        );

        $this->applyExtensionRevenue(
            $extensionRows,
            $rentalItemCounts,
            $extensionLineTotals,
            $metrics,
            $trend,
            $filters['from'],
            $filters['to'],
        );

        $maintenanceRows = $this->maintenanceRows($assetIds);
        $this->applyMaintenance(
            $maintenanceRows,
            $metrics,
            $quality,
            $trend,
            $filters['from'],
            $filters['to'],
        );

        $rows = $assets
            ->map(fn (stdClass $asset): array => $this->buildAssetRow(
                $asset,
                $metrics[(int) $asset->id],
                $filters['from'],
                $filters['to'],
            ))
            ->sortByDesc('period_revenue')
            ->values();

        $trend = $this->finalizeTrend($trend);

        return [
            'summary' => $this->summary($rows, $quality),
            'assets' => $rows->all(),
            'trend' => array_values($trend),
            'branches' => $this->branchPerformance($rows),
            'insights' => $this->insights($rows, $quality),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return Collection<int, stdClass>
     */
    private function assets(int $companyId, array $branchIds, array $filters): Collection
    {
        if ($branchIds === []) {
            return collect();
        }

        $maxRates = DB::table('product_rates')
            ->selectRaw('product_id, max(amount) as max_rental_rate')
            ->where('is_active', true)
            ->groupBy('product_id');

        return DB::table('assets as assets')
            ->join('products as products', 'products.id', '=', 'assets.product_id')
            ->join('branches as branches', 'branches.id', '=', 'assets.current_branch_id')
            ->leftJoin('product_categories as categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub(
                $maxRates,
                'rates',
                static fn ($join) => $join->on('rates.product_id', '=', 'products.id'),
            )
            ->where('products.company_id', $companyId)
            ->whereIn('assets.current_branch_id', $branchIds)
            ->whereNull('assets.deleted_at')
            ->whereNull('products.deleted_at')
            ->when(
                $filters['category_id'] !== null,
                fn ($query) => $query->where('products.category_id', $filters['category_id']),
            )
            ->when(
                $filters['status'] !== 'all',
                fn ($query) => $query->where('assets.status', $filters['status']),
            )
            ->when(
                $filters['condition'] !== 'all',
                fn ($query) => $query->where('assets.condition', $filters['condition']),
            )
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = $filters['search'];
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('assets.asset_code', 'like', "%{$search}%")
                        ->orWhere('assets.serial_number', 'like', "%{$search}%")
                        ->orWhere('products.name', 'like', "%{$search}%")
                        ->orWhere('products.sku', 'like', "%{$search}%")
                        ->orWhere('products.brand', 'like', "%{$search}%")
                        ->orWhere('products.model', 'like', "%{$search}%");
                });
            })
            ->select([
                'assets.id',
                'assets.product_id',
                'assets.current_branch_id',
                'assets.asset_code',
                'assets.serial_number',
                'assets.status',
                'assets.condition',
                'assets.purchase_date',
                'assets.purchase_price',
                'assets.replacement_value',
                'assets.is_active',
                'assets.created_at',
                'products.sku as product_sku',
                'products.name as product_name',
                'products.brand as product_brand',
                'products.model as product_model',
                'categories.id as category_id',
                'categories.name as category_name',
                'branches.code as branch_code',
                'branches.name as branch_name',
                'rates.max_rental_rate',
            ])
            ->orderBy('products.name')
            ->orderBy('assets.asset_code')
            ->get();
    }

    /** @param list<int> $assetIds */
    private function rentalRows(array $assetIds): Collection
    {
        return DB::table('rental_item_assets as assignments')
            ->join('rental_items as items', 'items.id', '=', 'assignments.rental_item_id')
            ->join('rentals as rentals', 'rentals.id', '=', 'items.rental_id')
            ->whereIn('assignments.asset_id', $assetIds)
            ->whereNull('rentals.deleted_at')
            ->whereNotIn('rentals.status', ['draft', 'cancelled', 'void', 'rejected'])
            ->select([
                'assignments.id as assignment_id',
                'assignments.asset_id',
                'assignments.rental_item_id',
                'assignments.checked_out_at as assignment_checked_out_at',
                'assignments.returned_at as assignment_returned_at',
                'assignments.status as assignment_status',
                'items.rental_id',
                'items.quantity',
                'items.total_amount',
                'items.created_at as item_created_at',
                'rentals.status as rental_status',
                'rentals.legacy_number',
                'rentals.checked_out_at as rental_checked_out_at',
                'rentals.due_at as rental_due_at',
                'rentals.returned_at as rental_returned_at',
                'rentals.discount_amount as rental_discount_amount',
            ])
            ->get();
    }

    /**
     * @param Collection<int, stdClass> $rentalRows
     * @return array<int, int>
     */
    private function rentalItemAssetCounts(
        Collection $rentalRows,
        Collection $extensionRows,
    ): array {
        /** @var list<int> $itemIds */
        $itemIds = $rentalRows
            ->pluck('rental_item_id')
            ->merge($extensionRows->pluck('rental_item_id'))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($itemIds === []) {
            return [];
        }

        return DB::table('rental_item_assets')
            ->whereIn('rental_item_id', $itemIds)
            ->selectRaw('rental_item_id, count(*) as asset_count')
            ->groupBy('rental_item_id')
            ->pluck('asset_count', 'rental_item_id')
            ->mapWithKeys(static fn ($count, $itemId): array => [(int) $itemId => max((int) $count, 1)])
            ->all();
    }

    /**
     * @param Collection<int, stdClass> $rows
     * @return array<int, float>
     */
    private function rentalLineTotals(Collection $rows): array
    {
        /** @var list<int> $rentalIds */
        $rentalIds = $rows
            ->pluck('rental_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($rentalIds === []) {
            return [];
        }

        return DB::table('rental_items')
            ->whereIn('rental_id', $rentalIds)
            ->selectRaw('rental_id, sum(total_amount) as line_total')
            ->groupBy('rental_id')
            ->pluck('line_total', 'rental_id')
            ->mapWithKeys(static fn ($total, $rentalId): array => [(int) $rentalId => (float) $total])
            ->all();
    }

    /**
     * @param Collection<int, stdClass> $rows
     * @return array<int, float>
     */
    private function extensionLineTotals(Collection $rows): array
    {
        /** @var list<int> $extensionIds */
        $extensionIds = $rows
            ->pluck('extension_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($extensionIds === []) {
            return [];
        }

        return DB::table('rental_extension_items')
            ->whereIn('rental_extension_id', $extensionIds)
            ->selectRaw('rental_extension_id, sum(total_amount) as line_total')
            ->groupBy('rental_extension_id')
            ->pluck('line_total', 'rental_extension_id')
            ->mapWithKeys(static fn ($total, $extensionId): array => [(int) $extensionId => (float) $total])
            ->all();
    }

    /** @param list<int> $assetIds */
    private function extensionRows(array $assetIds): Collection
    {
        return DB::table('rental_extension_items as extension_items')
            ->join('rental_extensions as extensions', 'extensions.id', '=', 'extension_items.rental_extension_id')
            ->join('rental_items as items', 'items.id', '=', 'extension_items.rental_item_id')
            ->join('rental_item_assets as assignments', 'assignments.rental_item_id', '=', 'items.id')
            ->whereIn('assignments.asset_id', $assetIds)
            ->whereIn('extensions.status', ['approved', 'completed'])
            ->select([
                'assignments.asset_id',
                'items.id as rental_item_id',
                'extensions.id as extension_id',
                'extensions.status as extension_status',
                'extension_items.total_amount',
                'extensions.approved_at',
                'extensions.created_at',
                'extensions.discount_amount as extension_discount_amount',
            ])
            ->get();
    }

    /** @param list<int> $assetIds */
    private function maintenanceRows(array $assetIds): Collection
    {
        return DB::table('maintenance_orders')
            ->whereIn('asset_id', $assetIds)
            ->select([
                'id',
                'asset_id',
                'status',
                'actual_cost',
                'reported_at',
                'started_at',
                'completed_at',
            ])
            ->get();
    }

    /**
     * @param Collection<int, stdClass> $rows
     * @param array<int, int> $itemCounts
     * @param array<int, float> $rentalLineTotals
     * @param array<int, array<string, mixed>> $metrics
     * @param array<string, array<string, mixed>> $trend
     */
    private function applyRentalRevenueAndIntervals(
        Collection $rows,
        array $itemCounts,
        array $rentalLineTotals,
        array &$metrics,
        array &$quality,
        array &$trend,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        $effectiveNow = CarbonImmutable::now();
        $analysisEnd = $to->lessThan($effectiveNow) ? $to : $effectiveNow;

        foreach ($rows as $row) {
            $assetId = (int) $row->asset_id;
            $itemId = (int) $row->rental_item_id;
            $rentalId = (int) $row->rental_id;
            $rentalStatus = mb_strtolower((string) $row->rental_status);
            $grossItemRevenue = $this->money($row->total_amount);
            $lineTotal = $rentalLineTotals[$rentalId] ?? $grossItemRevenue;
            $headerDiscount = $this->money($row->rental_discount_amount);
            $itemDiscountShare = $lineTotal > 0
                ? min($headerDiscount * ($grossItemRevenue / $lineTotal), $grossItemRevenue)
                : 0.0;
            $netItemRevenue = max($grossItemRevenue - $itemDiscountShare, 0);
            $allocation = $netItemRevenue / ($itemCounts[$itemId] ?? 1);
            $revenueAt = $this->dateTime(
                $row->assignment_checked_out_at
                    ?? $row->rental_checked_out_at
                    ?? $row->item_created_at,
            );

            if ($rentalStatus === 'returned') {
                $metrics[$assetId]['lifetime_revenue'] += $allocation;
                $metrics[$assetId]['realized_rental_ids'][$rentalId] = true;

                if ($revenueAt !== null && $revenueAt->betweenIncluded($from, $to)) {
                    $metrics[$assetId]['period_revenue'] += $allocation;
                    $month = $revenueAt->format('Y-m');

                    if (isset($trend[$month])) {
                        $trend[$month]['revenue'] += $allocation;
                    }
                }
            } else {
                $metrics[$assetId]['open_lifetime_revenue'] += $allocation;
                $metrics[$assetId]['open_rental_ids'][$rentalId] = true;

                if ($revenueAt !== null && $revenueAt->betweenIncluded($from, $to)) {
                    $metrics[$assetId]['open_period_revenue'] += $allocation;
                }
            }

            $checkedOutAt = $this->dateTime(
                $row->assignment_checked_out_at ?? $row->rental_checked_out_at,
            );

            if ($checkedOutAt === null) {
                continue;
            }

            $returnedAt = $this->dateTime(
                $row->assignment_returned_at ?? $row->rental_returned_at,
            );
            $dueAt = $this->dateTime($row->rental_due_at);
            $intervalEnd = null;

            if ($rentalStatus === 'returned') {
                if ($returnedAt !== null && $returnedAt->greaterThan($checkedOutAt)) {
                    $intervalEnd = $returnedAt;
                } elseif ($dueAt !== null && $dueAt->greaterThan($checkedOutAt)) {
                    $intervalEnd = $dueAt;
                    $metrics[$assetId]['invalid_interval_count']++;
                    $quality['invalid_assignment_ids'][(int) $row->assignment_id] = true;
                } else {
                    $metrics[$assetId]['invalid_interval_count']++;
                    $quality['invalid_assignment_ids'][(int) $row->assignment_id] = true;
                }
            } else {
                $isLegacy = $row->legacy_number !== null && $row->legacy_number !== '';

                if (
                    $isLegacy
                    && $dueAt !== null
                    && $dueAt->greaterThan($checkedOutAt)
                ) {
                    $intervalEnd = $dueAt;

                    if ($dueAt->lessThan($effectiveNow)) {
                        $metrics[$assetId]['stale_active_rental_ids'][$rentalId] = true;
                        $quality['stale_active_rental_ids'][$rentalId] = true;
                    }
                } else {
                    $intervalEnd = $analysisEnd;
                }
            }

            if ($intervalEnd !== null && $intervalEnd->greaterThan($checkedOutAt)) {
                $metrics[$assetId]['rental_intervals'][] = [
                    $checkedOutAt,
                    $intervalEnd,
                ];
            }
        }
    }

    /**
     * @param Collection<int, stdClass> $rows
     * @param array<int, int> $itemCounts
     * @param array<int, float> $extensionLineTotals
     * @param array<int, array<string, mixed>> $metrics
     * @param array<string, array<string, mixed>> $trend
     */
    private function applyExtensionRevenue(
        Collection $rows,
        array $itemCounts,
        array $extensionLineTotals,
        array &$metrics,
        array &$trend,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        foreach ($rows as $row) {
            $assetId = (int) $row->asset_id;
            $itemId = (int) $row->rental_item_id;
            $extensionId = (int) $row->extension_id;
            $extensionStatus = mb_strtolower((string) $row->extension_status);
            $grossItemRevenue = $this->money($row->total_amount);
            $lineTotal = $extensionLineTotals[$extensionId] ?? $grossItemRevenue;
            $headerDiscount = $this->money($row->extension_discount_amount);
            $itemDiscountShare = $lineTotal > 0
                ? min($headerDiscount * ($grossItemRevenue / $lineTotal), $grossItemRevenue)
                : 0.0;
            $netItemRevenue = max($grossItemRevenue - $itemDiscountShare, 0);
            $allocation = $netItemRevenue / ($itemCounts[$itemId] ?? 1);
            $revenueAt = $this->dateTime($row->approved_at ?? $row->created_at);

            if ($extensionStatus === 'completed') {
                $metrics[$assetId]['lifetime_revenue'] += $allocation;

                if ($revenueAt !== null && $revenueAt->betweenIncluded($from, $to)) {
                    $metrics[$assetId]['period_revenue'] += $allocation;
                    $month = $revenueAt->format('Y-m');

                    if (isset($trend[$month])) {
                        $trend[$month]['revenue'] += $allocation;
                    }
                }

                continue;
            }

            $metrics[$assetId]['open_lifetime_revenue'] += $allocation;

            if ($revenueAt !== null && $revenueAt->betweenIncluded($from, $to)) {
                $metrics[$assetId]['open_period_revenue'] += $allocation;
            }
        }
    }

    /**
     * @param Collection<int, stdClass> $rows
     * @param array<int, array<string, mixed>> $metrics
     * @param array<string, array<string, mixed>> $trend
     */
    private function applyMaintenance(
        Collection $rows,
        array &$metrics,
        array &$quality,
        array &$trend,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): void {
        foreach ($rows as $row) {
            $assetId = (int) $row->asset_id;
            $status = mb_strtolower((string) $row->status);
            $completedAt = $this->dateTime($row->completed_at);
            $isCompleted = in_array($status, ['completed', 'closed'], true) && $completedAt !== null;

            $quality['maintenance_record_ids'][(int) $row->id] = true;

            if ($isCompleted) {
                $quality['completed_maintenance_ids'][(int) $row->id] = true;
                $cost = $this->money($row->actual_cost);
                $metrics[$assetId]['maintenance_cost'] += $cost;

                if ($completedAt->betweenIncluded($from, $to)) {
                    $metrics[$assetId]['period_maintenance_cost'] += $cost;
                    $month = $completedAt->format('Y-m');

                    if (isset($trend[$month])) {
                        $trend[$month]['maintenance'] += $cost;
                    }
                }
            }

            if (in_array($status, ['cancelled', 'void', 'rejected'], true)) {
                continue;
            }

            $startedAt = $this->dateTime($row->started_at);

            if ($startedAt === null && $isCompleted) {
                $startedAt = $this->dateTime($row->reported_at);
            }

            if ($startedAt !== null) {
                $metrics[$assetId]['maintenance_intervals'][] = [
                    $startedAt,
                    $completedAt ?? $to,
                ];
            }
        }
    }

    /**
     * @param array<string, mixed> $metric
     * @return array<string, mixed>
     */
    private function buildAssetRow(
        stdClass $asset,
        array $metric,
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): array {
        $purchasePrice = $this->money($asset->purchase_price);
        $maxRentalRate = $this->money($asset->max_rental_rate);
        $purchasePriceSuspicious = $purchasePrice > 0
            && $maxRentalRate > 0
            && $purchasePrice < $maxRentalRate;
        $lifetimeRevenue = (float) $metric['lifetime_revenue'];
        $periodRevenue = (float) $metric['period_revenue'];
        $openLifetimeRevenue = (float) $metric['open_lifetime_revenue'];
        $openPeriodRevenue = (float) $metric['open_period_revenue'];
        $maintenanceCost = (float) $metric['maintenance_cost'];
        $netContribution = $lifetimeRevenue - $maintenanceCost;
        $netProfit = $netContribution - $purchasePrice;
        $bepProgress = $purchasePrice > 0 ? ($netContribution / $purchasePrice) * 100 : null;
        $roi = $purchasePrice > 0 ? ($netProfit / $purchasePrice) * 100 : null;
        $remaining = $purchasePrice > 0 ? max($purchasePrice - $netContribution, 0) : 0.0;
        $operationalStart = $this->operationalStart($asset, $from);
        $availableSeconds = 0;
        $rentedSeconds = 0;

        if ($operationalStart->lessThanOrEqualTo($to)) {
            $totalSeconds = $operationalStart->diffInSeconds($to);
            $maintenanceSeconds = $this->intervalSeconds(
                $metric['maintenance_intervals'],
                $operationalStart,
                $to,
            );
            $availableSeconds = max($totalSeconds - $maintenanceSeconds, 0);
            $rentedSeconds = min(
                $this->intervalSeconds($metric['rental_intervals'], $operationalStart, $to),
                $availableSeconds,
            );
        }

        $utilization = $availableSeconds > 0
            ? min(($rentedSeconds / $availableSeconds) * 100, 100)
            : 0.0;
        $periodMonths = max($from->diffInSeconds($to) / 2_629_746, 1 / 30);
        $periodNet = $periodRevenue - (float) $metric['period_maintenance_cost'];
        $monthlyNetRunRate = $periodNet / $periodMonths;
        $estimatedBepMonths = $remaining > 0 && $monthlyNetRunRate > 0
            ? $remaining / $monthlyNetRunRate
            : null;
        $ageStart = $asset->purchase_date !== null
            ? CarbonImmutable::parse((string) $asset->purchase_date)->startOfDay()
            : CarbonImmutable::parse((string) $asset->created_at);
        $ageDays = (int) max($ageStart->diffInDays($to, false), 0);
        $maintenanceRatio = $lifetimeRevenue > 0
            ? $maintenanceCost / $lifetimeRevenue
            : ($maintenanceCost > 0 ? 1.0 : 0.0);
        $staleActiveRentalCount = count($metric['stale_active_rental_ids']);
        $realizedRentalCount = count($metric['realized_rental_ids']);
        $openRentalCount = count($metric['open_rental_ids']);
        $rentalCount = $realizedRentalCount + $openRentalCount;
        $dataQuality = $this->dataQuality(
            $purchasePrice,
            $purchasePriceSuspicious,
            (int) $metric['invalid_interval_count'],
            $staleActiveRentalCount,
            $rentalCount,
        );
        $recommendation = $this->businessRecommendation(
            $purchasePrice,
            $bepProgress,
            $utilization,
            $maintenanceRatio,
            (string) $asset->condition,
            $ageDays,
            $periodRevenue,
            $dataQuality,
        );

        return [
            'id' => (int) $asset->id,
            'asset_code' => (string) $asset->asset_code,
            'serial_number' => $asset->serial_number,
            'product' => [
                'id' => (int) $asset->product_id,
                'sku' => (string) $asset->product_sku,
                'name' => (string) $asset->product_name,
                'brand' => $asset->product_brand,
                'model' => $asset->product_model,
            ],
            'category' => $asset->category_id === null ? null : [
                'id' => (int) $asset->category_id,
                'name' => (string) $asset->category_name,
            ],
            'branch' => [
                'id' => (int) $asset->current_branch_id,
                'code' => (string) $asset->branch_code,
                'name' => (string) $asset->branch_name,
            ],
            'status' => (string) $asset->status,
            'condition' => (string) $asset->condition,
            'is_active' => (bool) $asset->is_active,
            'purchase_date' => $asset->purchase_date,
            'purchase_price' => round($purchasePrice, 2),
            'replacement_value' => round($this->money($asset->replacement_value), 2),
            'max_rental_rate' => round($maxRentalRate, 2),
            'purchase_price_suspicious' => $purchasePriceSuspicious,
            'lifetime_revenue' => round($lifetimeRevenue, 2),
            'period_revenue' => round($periodRevenue, 2),
            'open_lifetime_revenue' => round($openLifetimeRevenue, 2),
            'open_period_revenue' => round($openPeriodRevenue, 2),
            'maintenance_cost' => round($maintenanceCost, 2),
            'period_maintenance_cost' => round((float) $metric['period_maintenance_cost'], 2),
            'net_contribution' => round($netContribution, 2),
            'net_profit' => round($netProfit, 2),
            'roi_percent' => $roi === null ? null : round($roi, 2),
            'bep_progress_percent' => $bepProgress === null ? null : round($bepProgress, 2),
            'remaining_to_bep' => round($remaining, 2),
            'utilization_percent' => round($utilization, 2),
            'rental_count' => $rentalCount,
            'realized_rental_count' => $realizedRentalCount,
            'open_rental_count' => $openRentalCount,
            'invalid_interval_count' => (int) $metric['invalid_interval_count'],
            'stale_active_rental_count' => $staleActiveRentalCount,
            'rented_hours' => round($rentedSeconds / 3600, 2),
            'available_hours' => round($availableSeconds / 3600, 2),
            'monthly_net_run_rate' => round($monthlyNetRunRate, 2),
            'estimated_bep_months' => $estimatedBepMonths === null
                ? null
                : round($estimatedBepMonths, 1),
            'data_quality' => $dataQuality,
            'recommendation' => $recommendation,
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function summary(Collection $rows, array $quality): array
    {
        $investment = (float) $rows->sum('purchase_price');
        $revenue = (float) $rows->sum('lifetime_revenue');
        $periodRevenue = (float) $rows->sum('period_revenue');
        $openRevenue = (float) $rows->sum('open_lifetime_revenue');
        $openPeriodRevenue = (float) $rows->sum('open_period_revenue');
        $maintenance = (float) $rows->sum('maintenance_cost');
        $periodMaintenance = (float) $rows->sum('period_maintenance_cost');
        $netContribution = $revenue - $maintenance;
        $netProfit = $netContribution - $investment;
        $activeRows = $rows->where('is_active', true);
        $availableHours = (float) $activeRows->sum('available_hours');
        $rentedHours = (float) $activeRows->sum('rented_hours');
        $businessActionCodes = [
            'service_review',
            'add_capacity',
            'review_disposal',
            'promote',
        ];
        $assetCount = $rows->count();
        $pricedAssetCount = $rows->where('purchase_price', '>', 0)->count();
        $suspiciousPurchasePriceCount = $rows
            ->where('purchase_price_suspicious', true)
            ->count();

        return [
            'asset_count' => $assetCount,
            'active_asset_count' => $activeRows->count(),
            'priced_asset_count' => $pricedAssetCount,
            'investment_coverage_percent' => $assetCount > 0
                ? round(($pricedAssetCount / $assetCount) * 100, 2)
                : 0.0,
            'investment_is_complete' => $assetCount > 0
                && $pricedAssetCount === $assetCount
                && $suspiciousPurchasePriceCount === 0,
            'total_investment' => round($investment, 2),
            'lifetime_revenue' => round($revenue, 2),
            'period_revenue' => round($periodRevenue, 2),
            'open_lifetime_revenue' => round($openRevenue, 2),
            'open_period_revenue' => round($openPeriodRevenue, 2),
            'maintenance_cost' => round($maintenance, 2),
            'period_maintenance_cost' => round($periodMaintenance, 2),
            'net_contribution' => round($netContribution, 2),
            'net_profit' => round($netProfit, 2),
            'roi_percent' => $investment > 0 ? round(($netProfit / $investment) * 100, 2) : null,
            'bep_progress_percent' => $investment > 0
                ? round(($netContribution / $investment) * 100, 2)
                : null,
            'bep_asset_count' => $rows
                ->filter(static fn (array $row): bool => ($row['bep_progress_percent'] ?? 0) >= 100)
                ->count(),
            'not_bep_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['purchase_price'] > 0 && ($row['bep_progress_percent'] ?? 0) < 100)
                ->count(),
            'utilization_percent' => $availableHours > 0
                ? round(min(($rentedHours / $availableHours) * 100, 100), 2)
                : 0.0,
            'missing_purchase_price_count' => $rows->where('purchase_price', '<=', 0)->count(),
            'suspicious_purchase_price_count' => $suspiciousPurchasePriceCount,
            'invalid_interval_count' => count($quality['invalid_assignment_ids']),
            'stale_active_rental_count' => count($quality['stale_active_rental_ids']),
            'maintenance_record_count' => count($quality['maintenance_record_ids']),
            'completed_maintenance_count' => count($quality['completed_maintenance_ids']),
            'data_quality_issue_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['data_quality']['issue_count'] > 0)
                ->count(),
            'data_quality_blocker_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['data_quality']['has_blocker'])
                ->count(),
            'high_quality_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['data_quality']['confidence'] === 'high')
                ->count(),
            'business_action_count' => $rows
                ->filter(static fn (array $row): bool => in_array(
                    $row['recommendation']['code'],
                    $businessActionCodes,
                    true,
                ))
                ->count(),
            'high_confidence_action_count' => $rows
                ->filter(static fn (array $row): bool => in_array(
                    $row['recommendation']['code'],
                    $businessActionCodes,
                    true,
                ))
                ->filter(static fn (array $row): bool => $row['recommendation']['confidence'] === 'high')
                ->count(),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function branchPerformance(Collection $rows): array
    {
        return $rows
            ->groupBy(static fn (array $row): int => (int) $row['branch']['id'])
            ->map(function (Collection $branchRows): array {
                /** @var array<string, mixed> $first */
                $first = $branchRows->first();
                $activeRows = $branchRows->where('is_active', true);
                $availableHours = (float) $activeRows->sum('available_hours');
                $rentedHours = (float) $activeRows->sum('rented_hours');
                $investment = (float) $branchRows->sum('purchase_price');
                $revenue = (float) $branchRows->sum('lifetime_revenue');
                $openRevenue = (float) $branchRows->sum('open_lifetime_revenue');
                $maintenance = (float) $branchRows->sum('maintenance_cost');
                $net = $revenue - $maintenance;

                return [
                    'branch' => $first['branch'],
                    'asset_count' => $branchRows->count(),
                    'active_asset_count' => $activeRows->count(),
                    'investment' => round($investment, 2),
                    'revenue' => round($revenue, 2),
                    'open_revenue' => round($openRevenue, 2),
                    'maintenance_cost' => round($maintenance, 2),
                    'net_contribution' => round($net, 2),
                    'bep_progress_percent' => $investment > 0 ? round(($net / $investment) * 100, 2) : null,
                    'utilization_percent' => $availableHours > 0
                        ? round(min(($rentedHours / $availableHours) * 100, 100), 2)
                        : 0.0,
                ];
            })
            ->sortByDesc('net_contribution')
            ->values()
            ->all();
    }

    /** @param Collection<int, array<string, mixed>> $rows */
    private function insights(Collection $rows, array $quality): array
    {
        $topRevenue = $rows
            ->filter(static fn (array $row): bool => $row['period_revenue'] > 0)
            ->sortByDesc('period_revenue')
            ->first();
        $topUtilization = $rows
            ->filter(static fn (array $row): bool => $row['is_active'])
            ->filter(static fn (array $row): bool => ! $row['data_quality']['usage_blocked'])
            ->sortByDesc('utilization_percent')
            ->first();
        $highestMaintenance = $rows
            ->filter(static fn (array $row): bool => $row['maintenance_cost'] > 0)
            ->sortByDesc('maintenance_cost')
            ->first();
        $businessActionCodes = [
            'service_review',
            'add_capacity',
            'review_disposal',
            'promote',
        ];
        $businessActions = $rows->filter(static fn (array $row): bool => in_array(
            $row['recommendation']['code'],
            $businessActionCodes,
            true,
        ));

        return [
            'top_revenue' => $topRevenue,
            'top_utilization' => $topUtilization,
            'highest_maintenance' => $highestMaintenance,
            'zero_revenue_count' => $rows->where('lifetime_revenue', '<=', 0)->count(),
            'missing_purchase_price_count' => $rows->where('purchase_price', '<=', 0)->count(),
            'suspicious_purchase_price_count' => $rows->where('purchase_price_suspicious', true)->count(),
            'invalid_interval_count' => count($quality['invalid_assignment_ids']),
            'stale_active_rental_count' => count($quality['stale_active_rental_ids']),
            'maintenance_record_count' => count($quality['maintenance_record_ids']),
            'data_quality_issue_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['data_quality']['issue_count'] > 0)
                ->count(),
            'data_quality_blocker_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['data_quality']['has_blocker'])
                ->count(),
            'high_quality_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['data_quality']['confidence'] === 'high')
                ->count(),
            'business_action_count' => $businessActions->count(),
            'high_confidence_action_count' => $businessActions
                ->filter(static fn (array $row): bool => $row['recommendation']['confidence'] === 'high')
                ->count(),
            'add_capacity_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'add_capacity')
                ->count(),
            'service_review_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'service_review')
                ->count(),
            'review_disposal_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'review_disposal')
                ->count(),
            'promote_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'promote')
                ->count(),
            'healthy_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'profitable')
                ->count(),
            'monitor_asset_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'monitor')
                ->count(),
            'deferred_decision_count' => $rows
                ->filter(static fn (array $row): bool => $row['recommendation']['code'] === 'decision_deferred')
                ->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dataQuality(
        float $purchasePrice,
        bool $purchasePriceSuspicious,
        int $invalidIntervalCount,
        int $staleActiveRentalCount,
        int $rentalCount,
    ): array {
        $issues = [];
        $score = 100;
        $investmentBlocked = false;
        $usageBlocked = false;
        $safeRentalCount = max($rentalCount, 1);
        $invalidRatio = ($invalidIntervalCount / $safeRentalCount) * 100;
        $staleRatio = ($staleActiveRentalCount / $safeRentalCount) * 100;

        if ($purchasePrice <= 0) {
            $score -= 45;
            $investmentBlocked = true;
            $issues[] = [
                'code' => 'missing_purchase_price',
                'label' => 'Harga beli belum tersedia',
                'description' => 'ROI dan BEP belum dapat dinilai sampai nilai investasi dilengkapi.',
                'severity' => 'danger',
                'count' => 1,
            ];
        } elseif ($purchasePriceSuspicious) {
            $score -= 35;
            $investmentBlocked = true;
            $issues[] = [
                'code' => 'suspicious_purchase_price',
                'label' => 'Harga beli perlu validasi',
                'description' => 'Harga beli lebih rendah daripada tarif sewa tertinggi produk.',
                'severity' => 'danger',
                'count' => 1,
            ];
        }

        if ($invalidIntervalCount > 0) {
            $invalidIsBlocker = $invalidRatio >= 10;
            $usageBlocked = $usageBlocked || $invalidIsBlocker;
            $score -= $invalidIsBlocker
                ? ($invalidRatio >= 25 ? 35 : 25)
                : 8;
            $issues[] = [
                'code' => 'invalid_intervals',
                'label' => 'Interval memakai fallback',
                'description' => $invalidIsBlocker
                    ? 'Porsi interval bermasalah mencapai sedikitnya 10% dari histori rental.'
                    : 'Ada interval lama yang dikoreksi dengan due date, tetapi porsinya masih kecil.',
                'severity' => $invalidIsBlocker ? 'danger' : 'warning',
                'count' => $invalidIntervalCount,
            ];
        }

        if ($staleActiveRentalCount > 0) {
            $staleIsBlocker = $staleActiveRentalCount >= 3 && $staleRatio >= 10;
            $usageBlocked = $usageBlocked || $staleIsBlocker;
            $score -= $staleIsBlocker ? 25 : 8;
            $issues[] = [
                'code' => 'stale_active_rentals',
                'label' => 'Rental aktif legacy kedaluwarsa',
                'description' => $staleIsBlocker
                    ? 'Jumlah dan porsi rental aktif kedaluwarsa cukup besar untuk memengaruhi keputusan utilisasi.'
                    : 'Ada transaksi legacy aktif kedaluwarsa, tetapi tidak mendominasi histori unit.',
                'severity' => $staleIsBlocker ? 'danger' : 'warning',
                'count' => $staleActiveRentalCount,
            ];
        }

        $score = max(min($score, 100), 0);
        $hasBlocker = $investmentBlocked || $usageBlocked;
        $confidence = match (true) {
            $hasBlocker || $score < 60 => 'low',
            $score < 85 => 'medium',
            default => 'high',
        };

        return [
            'score' => $score,
            'confidence' => $confidence,
            'confidence_label' => $this->confidenceLabel($confidence),
            'tone' => match ($confidence) {
                'high' => 'success',
                'medium' => 'warning',
                default => 'danger',
            },
            'has_blocker' => $hasBlocker,
            'investment_blocked' => $investmentBlocked,
            'usage_blocked' => $usageBlocked,
            'invalid_interval_ratio_percent' => round($invalidRatio, 2),
            'stale_active_ratio_percent' => round($staleRatio, 2),
            'issue_count' => count($issues),
            'issues' => $issues,
        ];
    }

    /**
     * @param array<string, mixed> $dataQuality
     * @return array<string, mixed>
     */
    private function businessRecommendation(
        float $purchasePrice,
        ?float $bepProgress,
        float $utilization,
        float $maintenanceRatio,
        string $condition,
        int $ageDays,
        float $periodRevenue,
        array $dataQuality,
    ): array {
        $normalizedCondition = mb_strtolower($condition);

        if (in_array($normalizedCondition, ['poor', 'damaged', 'critical', 'rusak'], true)) {
            return $this->recommendationPayload(
                'service_review',
                'Evaluasi servis',
                'danger',
                'Kondisi unit membutuhkan pemeriksaan teknis sebelum dipertahankan dalam operasional.',
                'high',
            );
        }

        if ($maintenanceRatio >= 0.25) {
            return $this->recommendationPayload(
                'service_review',
                'Evaluasi servis',
                'danger',
                'Rasio biaya maintenance terhadap pendapatan sudah tinggi dan perlu evaluasi ekonomi.',
                $dataQuality['investment_blocked'] ? 'medium' : 'high',
            );
        }

        if (
            ! $dataQuality['usage_blocked']
            && $utilization >= 75
            && $periodRevenue > 0
        ) {
            return $this->recommendationPayload(
                'add_capacity',
                'Pertimbangkan tambah unit',
                'success',
                'Utilisasi dan pendapatan terealisasi menunjukkan permintaan yang kuat pada periode terpilih.',
                $dataQuality['confidence'] === 'high' ? 'high' : 'medium',
            );
        }

        if (
            ! $dataQuality['usage_blocked']
            && ! $dataQuality['investment_blocked']
            && $ageDays >= 365
            && $utilization < 10
            && ($bepProgress ?? 0) < 50
        ) {
            return $this->recommendationPayload(
                'review_disposal',
                'Evaluasi penjualan',
                'danger',
                'Aset berusia lebih dari satu tahun dengan utilisasi dan progres BEP yang masih rendah sehingga kelayakan untuk dipertahankan perlu ditinjau.',
                $dataQuality['confidence'],
            );
        }

        if (
            ! $dataQuality['usage_blocked']
            && $utilization < 20
            && $periodRevenue <= 0
        ) {
            return $this->recommendationPayload(
                'promote',
                'Promosikan aset',
                'warning',
                'Belum ada pendapatan terealisasi pada periode dan utilisasi unit masih rendah. Prioritaskan promosi sebelum mempertimbangkan relokasi atau penjualan.',
                $dataQuality['confidence'] === 'high' ? 'high' : 'medium',
            );
        }

        if (
            ! $dataQuality['investment_blocked']
            && ($bepProgress ?? 0) >= 100
        ) {
            return $this->recommendationPayload(
                'profitable',
                'Pertahankan produktivitas',
                'success',
                'Aset telah melewati BEP berdasarkan pendapatan terealisasi.',
                $dataQuality['usage_blocked'] ? 'medium' : $dataQuality['confidence'],
            );
        }

        if ($purchasePrice <= 0 || $dataQuality['investment_blocked']) {
            return $this->recommendationPayload(
                'decision_deferred',
                'Tunda keputusan investasi',
                'neutral',
                'Keputusan jual, tambah, atau evaluasi BEP sebaiknya menunggu data investasi yang tervalidasi.',
                'low',
            );
        }

        return $this->recommendationPayload(
            'monitor',
            $dataQuality['usage_blocked']
                ? 'Pantau sambil validasi histori'
                : 'Pantau menuju BEP',
            'neutral',
            $dataQuality['usage_blocked']
                ? 'Sinyal operasional belum cukup kuat karena sebagian histori pemakaian masih memerlukan validasi.'
                : 'Performa masih dalam jalur normal; pantau pendapatan, utilisasi, dan maintenance.',
            $dataQuality['usage_blocked'] ? 'low' : $dataQuality['confidence'],
        );
    }

    /**
     * @return array<string, string>
     */
    private function recommendationPayload(
        string $code,
        string $label,
        string $tone,
        string $description,
        string $confidence,
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'tone' => $tone,
            'description' => $description,
            'confidence' => $confidence,
            'confidence_label' => $this->confidenceLabel($confidence),
        ];
    }

    private function confidenceLabel(string $confidence): string
    {
        return match ($confidence) {
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            default => 'Rendah',
        };
    }

    /**
     * @param list<array{0: CarbonImmutable, 1: CarbonImmutable}> $intervals
     */
    private function intervalSeconds(array $intervals, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $clipped = [];

        foreach ($intervals as [$start, $end]) {
            $start = $start->greaterThan($from) ? $start : $from;
            $end = $end->lessThan($to) ? $end : $to;

            if ($end->lessThanOrEqualTo($start)) {
                continue;
            }

            $clipped[] = [$start, $end];
        }

        usort(
            $clipped,
            static fn (array $left, array $right): int => $left[0]->getTimestamp() <=> $right[0]->getTimestamp(),
        );

        $merged = [];

        foreach ($clipped as [$start, $end]) {
            if ($merged === []) {
                $merged[] = [$start, $end];
                continue;
            }

            $lastIndex = array_key_last($merged);

            if ($start->lessThanOrEqualTo($merged[$lastIndex][1])) {
                if ($end->greaterThan($merged[$lastIndex][1])) {
                    $merged[$lastIndex][1] = $end;
                }

                continue;
            }

            $merged[] = [$start, $end];
        }

        return (int) array_sum(array_map(
            static fn (array $interval): int => $interval[0]->diffInSeconds($interval[1]),
            $merged,
        ));
    }

    private function operationalStart(stdClass $asset, CarbonImmutable $from): CarbonImmutable
    {
        $start = $asset->purchase_date !== null
            ? CarbonImmutable::parse((string) $asset->purchase_date)->startOfDay()
            : CarbonImmutable::parse((string) $asset->created_at);

        return $start->greaterThan($from) ? $start : $from;
    }

    private function dateTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function money(mixed $value): float
    {
        return round((float) ($value ?? 0), 2);
    }

    /** @param list<int> $assetIds */
    private function blankMetrics(array $assetIds): array
    {
        $metrics = [];

        foreach ($assetIds as $assetId) {
            $metrics[$assetId] = [
                'lifetime_revenue' => 0.0,
                'period_revenue' => 0.0,
                'open_lifetime_revenue' => 0.0,
                'open_period_revenue' => 0.0,
                'maintenance_cost' => 0.0,
                'period_maintenance_cost' => 0.0,
                'realized_rental_ids' => [],
                'open_rental_ids' => [],
                'stale_active_rental_ids' => [],
                'invalid_interval_count' => 0,
                'rental_intervals' => [],
                'maintenance_intervals' => [],
            ];
        }

        return $metrics;
    }

    /**
     * @return array{
     *     invalid_assignment_ids: array<int, bool>,
     *     stale_active_rental_ids: array<int, bool>,
     *     maintenance_record_ids: array<int, bool>,
     *     completed_maintenance_ids: array<int, bool>
     * }
     */
    private function blankQuality(): array
    {
        return [
            'invalid_assignment_ids' => [],
            'stale_active_rental_ids' => [],
            'maintenance_record_ids' => [],
            'completed_maintenance_ids' => [],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function blankTrend(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $trend = [];
        $cursor = $from->startOfMonth();
        $last = $to->startOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            $key = $cursor->format('Y-m');
            $trend[$key] = [
                'key' => $key,
                'label' => $cursor->translatedFormat('M Y'),
                'revenue' => 0.0,
                'maintenance' => 0.0,
                'net' => 0.0,
            ];
            $cursor = $cursor->addMonth();
        }

        foreach ($trend as $key => $item) {
            $trend[$key]['revenue'] = round((float) $item['revenue'], 2);
            $trend[$key]['maintenance'] = round((float) $item['maintenance'], 2);
            $trend[$key]['net'] = round((float) $item['revenue'] - (float) $item['maintenance'], 2);
        }

        return $trend;
    }


    /**
     * @param array<string, array<string, mixed>> $trend
     * @return array<string, array<string, mixed>>
     */
    private function finalizeTrend(array $trend): array
    {
        foreach ($trend as $key => $item) {
            $revenue = round((float) $item['revenue'], 2);
            $maintenance = round((float) $item['maintenance'], 2);
            $trend[$key]['revenue'] = $revenue;
            $trend[$key]['maintenance'] = $maintenance;
            $trend[$key]['net'] = round($revenue - $maintenance, 2);
        }

        return $trend;
    }

    /** @return array<string, mixed> */
    private function emptyResult(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'summary' => [
                'asset_count' => 0,
                'active_asset_count' => 0,
                'priced_asset_count' => 0,
                'investment_coverage_percent' => 0.0,
                'investment_is_complete' => false,
                'total_investment' => 0.0,
                'lifetime_revenue' => 0.0,
                'period_revenue' => 0.0,
                'open_lifetime_revenue' => 0.0,
                'open_period_revenue' => 0.0,
                'maintenance_cost' => 0.0,
                'period_maintenance_cost' => 0.0,
                'net_contribution' => 0.0,
                'net_profit' => 0.0,
                'roi_percent' => null,
                'bep_progress_percent' => null,
                'bep_asset_count' => 0,
                'not_bep_asset_count' => 0,
                'utilization_percent' => 0.0,
                'missing_purchase_price_count' => 0,
                'suspicious_purchase_price_count' => 0,
                'invalid_interval_count' => 0,
                'stale_active_rental_count' => 0,
                'maintenance_record_count' => 0,
                'completed_maintenance_count' => 0,
                'data_quality_issue_asset_count' => 0,
                'data_quality_blocker_asset_count' => 0,
                'high_quality_asset_count' => 0,
                'business_action_count' => 0,
                'high_confidence_action_count' => 0,
            ],
            'assets' => [],
            'trend' => array_values($this->blankTrend($from, $to)),
            'branches' => [],
            'insights' => [
                'top_revenue' => null,
                'top_utilization' => null,
                'highest_maintenance' => null,
                'zero_revenue_count' => 0,
                'missing_purchase_price_count' => 0,
                'suspicious_purchase_price_count' => 0,
                'invalid_interval_count' => 0,
                'stale_active_rental_count' => 0,
                'maintenance_record_count' => 0,
                'data_quality_issue_asset_count' => 0,
                'data_quality_blocker_asset_count' => 0,
                'high_quality_asset_count' => 0,
                'business_action_count' => 0,
                'high_confidence_action_count' => 0,
                'add_capacity_count' => 0,
                'service_review_count' => 0,
                'review_disposal_count' => 0,
                'promote_count' => 0,
                'healthy_asset_count' => 0,
                'monitor_asset_count' => 0,
                'deferred_decision_count' => 0,
            ],
        ];
    }
}
