<?php

namespace App\Http\Controllers;

use App\Domain\Analytics\AssetAnalyticsService;
use App\Domain\Reporting\Stage6Presentation;
use App\Http\Requests\AssetAnalyticsRequest;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetAnalyticsController extends Controller
{
    public function index(
        AssetAnalyticsRequest $request,
        AssetAnalyticsService $analytics,
        Stage6Presentation $presentation,
    ): Response {
        Gate::authorize('reports.view');
        $actor = $request->user();
        /** @var list<int> $branchIds */
        $branchIds = $actor->accessibleBranches()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
        $filters = $this->filters($request, $branchIds);
        $result = $presentation->analytics($analytics->analyze((int) $actor->company_id, $branchIds, $filters));
        $page = max($request->integer('page', 1), 1);
        $perPage = 25;
        /** @var list<array<string, mixed>> $assetRows */
        $assetRows = $result['assets'];
        $rows = collect($assetRows);
        $assets = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('reports/asset-analytics', [
            'summary' => $result['summary'],
            'assets' => $assets,
            'trend' => $result['trend'],
            'branchPerformance' => $result['branches'],
            'insights' => $result['insights'],
            'filters' => [
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
                'branch_id' => $filters['branch_id'],
                'category_id' => $filters['category_id'],
                'status' => $filters['status'],
                'condition' => $filters['condition'],
                'search' => $filters['search'],
            ],
            'branches' => $actor->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'categories' => DB::table('product_categories')
                ->where('company_id', $actor->company_id)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'permissions' => [
                'export' => $actor->can('reports.export'),
            ],
            'methodology' => [
                'revenue' => __('uat035b_stage6.revenue'),
                'roi' => __('uat035b_stage6.roi'),
                'bep' => __('uat035b_stage6.bep'),
                'utilization' => __('uat035b_stage6.utilization'),
                'recommendation' => __('uat035b_stage6.recommendation'),
            ],
        ]);
    }

    public function export(
        AssetAnalyticsRequest $request,
        AssetAnalyticsService $analytics,
        Stage6Presentation $presentation,
    ): StreamedResponse {
        Gate::authorize('reports.export');
        $actor = $request->user();
        /** @var list<int> $branchIds */
        $branchIds = $actor->accessibleBranches()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
        $filters = $this->filters($request, $branchIds);
        $result = $presentation->analytics($analytics->analyze((int) $actor->company_id, $branchIds, $filters));
        $filename = sprintf(
            app()->getLocale() === 'en' ? 'asset-analytics-%s-to-%s.csv' : 'analitik-aset-%s-sampai-%s.csv',
            $filters['from']->toDateString(),
            $filters['to']->toDateString(),
        );

        return response()->streamDownload(function () use ($result, $presentation): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            $headers = [
                'Kode Aset',
                'Produk',
                'Cabang',
                'Status',
                'Kondisi',
                'Harga Beli',
                'Pendapatan Terealisasi Lifetime',
                'Pendapatan Terealisasi Periode',
                'Pendapatan Berjalan Lifetime',
                'Pendapatan Berjalan Periode',
                'Biaya Maintenance',
                'Kontribusi Bersih',
                'ROI (%)',
                'Progress BEP (%)',
                'Sisa Menuju BEP',
                'Utilisasi Periode (%)',
                'Jumlah Rental',
                'Rental Terealisasi',
                'Rental Berjalan',
                'Interval Fallback',
                'Rental Legacy Aktif Kedaluwarsa',
                'Harga Beli Mencurigakan',
                'Skor Kualitas Data',
                'Keyakinan Data',
                'Blocker Data',
                'Catatan Kualitas Data',
                'Estimasi Bulan BEP',
                'Rekomendasi Bisnis',
                'Keyakinan Rekomendasi',
            ];
            fputcsv($stream, array_map($presentation->text(...), $headers));

            foreach ($result['assets'] as $row) {
                fputcsv($stream, [
                    $row['asset_code'],
                    $row['product']['name'],
                    $row['branch']['name'],
                    Stage6Presentation::status((string) $row['status'], app()->getLocale()),
                    Stage6Presentation::status((string) $row['condition'], app()->getLocale()),
                    $row['purchase_price'],
                    $row['lifetime_revenue'],
                    $row['period_revenue'],
                    $row['open_lifetime_revenue'],
                    $row['open_period_revenue'],
                    $row['maintenance_cost'],
                    $row['net_contribution'],
                    $row['roi_percent'],
                    $row['bep_progress_percent'],
                    $row['remaining_to_bep'],
                    $row['utilization_percent'],
                    $row['rental_count'],
                    $row['realized_rental_count'],
                    $row['open_rental_count'],
                    $row['invalid_interval_count'],
                    $row['stale_active_rental_count'],
                    $presentation->text($row['purchase_price_suspicious'] ? 'Ya' : 'Tidak'),
                    $row['data_quality']['score'],
                    $row['data_quality']['confidence_label'],
                    $presentation->text($row['data_quality']['has_blocker'] ? 'Ya' : 'Tidak'),
                    collect((array) $row['data_quality']['issues'])
                        ->map(static fn (array $issue): string => sprintf(
                            '%s (%d)',
                            $issue['label'],
                            $issue['count'],
                        ))
                        ->implode('; '),
                    $row['estimated_bep_months'],
                    $row['recommendation']['label'],
                    $row['recommendation']['confidence_label'],
                ]);
            }

            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     branch_id: int|null,
     *     category_id: int|null,
     *     status: string,
     *     condition: string,
     *     search: string
     * }
     */
    private function filters(AssetAnalyticsRequest $request, array $branchIds): array
    {
        $actor = $request->user();
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay()
            : now()->toImmutable()->subYear()->addDay()->startOfDay();
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())->endOfDay()
            : now()->toImmutable()->endOfDay();
        $requestedBranchId = $request->integer('branch_id') ?: null;

        if ($requestedBranchId !== null && ! in_array($requestedBranchId, $branchIds, true)) {
            abort(403, 'Cabang tidak berada dalam cakupan akses pengguna.');
        }

        $branchId = $requestedBranchId;

        if ($branchId === null && ! $actor->hasCompanyScopedRole()) {
            $currentBranchId = (int) ($actor->current_branch_id ?? 0);
            $branchId = in_array($currentBranchId, $branchIds, true) ? $currentBranchId : null;
        }

        $status = $request->string('status')->toString();
        $condition = $request->string('condition')->toString();

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
            'category_id' => $request->integer('category_id') ?: null,
            'status' => $status === '' ? 'all' : $status,
            'condition' => $condition === '' ? 'all' : $condition,
            'search' => trim($request->string('search')->toString()),
        ];
    }
}
