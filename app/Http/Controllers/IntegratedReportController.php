<?php

namespace App\Http\Controllers;

use App\Domain\Reporting\IntegratedReportService;
use App\Domain\Reporting\SimplePdfExporter;
use App\Domain\Reporting\SpreadsheetXmlExporter;
use App\Http\Requests\IntegratedReportRequest;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type ReportFilters array{
 *     from: CarbonImmutable,
 *     to: CarbonImmutable,
 *     branch_id: int|null,
 *     report: string,
 *     status: string,
 *     payment_method_id: int|null,
 *     category_id: int|null,
 *     search: string
 * }
 */
class IntegratedReportController extends Controller
{
    public function index(
        IntegratedReportRequest $request,
        IntegratedReportService $reports,
    ): InertiaResponse {
        Gate::authorize('reports.view');
        $actor = $request->user();
        $branchIds = $this->branchIds($request);
        $filters = $this->filters($request, $branchIds);
        $result = $reports->generate((int) $actor->company_id, $branchIds, $filters);
        /** @var list<array<string, mixed>> $reportRows */
        $reportRows = $result['rows'];
        $rows = collect($reportRows);
        $page = max($request->integer('page', 1), 1);
        $perPage = 25;
        $result['rows'] = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('reports/index', [
            ...$result,
            'filters' => $this->serializedFilters($filters),
            'branches' => $actor->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'paymentMethods' => DB::table('payment_methods')
                ->where('company_id', $actor->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'financialCategories' => DB::table('financial_categories')
                ->where('company_id', $actor->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'type']),
            'permissions' => [
                'export' => $actor->can('reports.export'),
            ],
            'generatedAt' => now()->toIso8601String(),
            'tabs' => $this->tabs(),
        ]);
    }

    public function export(
        IntegratedReportRequest $request,
        IntegratedReportService $reports,
        SpreadsheetXmlExporter $spreadsheet,
        SimplePdfExporter $pdf,
    ): Response {
        Gate::authorize('reports.export');
        $actor = $request->user();
        $branchIds = $this->branchIds($request);
        $filters = $this->filters($request, $branchIds);
        $result = $reports->generate((int) $actor->company_id, $branchIds, $filters);
        $branch = $filters['branch_id'] === null
            ? 'Semua cabang dalam cakupan akses'
            : (string) $actor->accessibleBranches()->whereKey($filters['branch_id'])->value('name');
        /**
         * @var array{
         *     title: string,
         *     subtitle: string,
         *     summary: list<array{label: string, value: int|float, type: string, note: string}>,
         *     columns: list<array{key: string, label: string, type: string, export_width: int}>,
         *     rows: list<array{id: string, href: string, values: array<string, mixed>}>
         * } $document
         */
        $document = [
            'title' => 'Together Kamera · '.$result['reportMeta']['label'],
            'subtitle' => sprintf(
                'Periode %s s.d. %s · %s · Dibuat %s WIB',
                $filters['from']->format('d-m-Y'),
                $filters['to']->format('d-m-Y'),
                $branch,
                now()->format('d-m-Y H:i'),
            ),
            'summary' => $result['summary'],
            'columns' => $result['columns'],
            'rows' => $result['rows'],
        ];
        $slug = str($filters['report'])->slug()->toString();
        $period = $filters['from']->toDateString().'-'.$filters['to']->toDateString();

        if ($request->string('format')->toString() === 'pdf') {
            return response($pdf->render($document), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="laporan-%s-%s.pdf"', $slug, $period),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response($spreadsheet->render($document), 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => sprintf('attachment; filename="laporan-%s-%s.xls"', $slug, $period),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return list<int> */
    private function branchIds(IntegratedReportRequest $request): array
    {
        return array_values(
            $request->user()
                ->accessibleBranches()
                ->pluck('id')
                ->map(static fn ($id): int => (int) $id)
                ->values()
                ->all(),
        );
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     from: CarbonImmutable,
     *     to: CarbonImmutable,
     *     branch_id: int|null,
     *     report: string,
     *     status: string,
     *     payment_method_id: int|null,
     *     category_id: int|null,
     *     search: string
     * }
     */
    private function filters(IntegratedReportRequest $request, array $branchIds): array
    {
        $actor = $request->user();
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay()
            : now()->toImmutable()->startOfMonth()->startOfDay();
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

        $report = $request->string('report')->toString();
        $status = $request->string('status')->toString();

        return [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
            'report' => $report === '' ? 'operational' : $report,
            'status' => $status === '' ? 'all' : $status,
            'payment_method_id' => $request->integer('payment_method_id') ?: null,
            'category_id' => $request->integer('category_id') ?: null,
            'search' => trim($request->string('search')->toString()),
        ];
    }

    /**
     * @param  ReportFilters  $filters
     * @return array{
     *     from: string,
     *     to: string,
     *     branch_id: int|null,
     *     report: string,
     *     status: string,
     *     payment_method_id: int|null,
     *     category_id: int|null,
     *     search: string
     * }
     */
    private function serializedFilters(array $filters): array
    {
        return [
            ...$filters,
            'from' => $filters['from']->toDateString(),
            'to' => $filters['to']->toDateString(),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private function tabs(): array
    {
        return [
            ['key' => 'operational', 'label' => 'Rental & Return'],
            ['key' => 'finance', 'label' => 'Keuangan'],
            ['key' => 'receivables', 'label' => 'Piutang & Deposit'],
            ['key' => 'cash', 'label' => 'Sesi Kas'],
            ['key' => 'assets', 'label' => 'Aset & Maintenance'],
            ['key' => 'transfers', 'label' => 'Transfer Cabang'],
            ['key' => 'inventory-audits', 'label' => 'Stock Opname'],
        ];
    }
}
