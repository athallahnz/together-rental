<?php

namespace App\Http\Controllers\Finance;

use App\Domain\Finance\FinanceDashboardService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\FinanceDashboardRequest;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinanceDashboardController extends Controller
{
    public function __invoke(
        FinanceDashboardRequest $request,
        FinanceDashboardService $dashboard,
    ): Response {
        $actor = $request->user();
        $branches = $actor->accessibleBranches()
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        /** @var list<int> $branchIds */
        $branchIds = array_values($branches->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all());
        $requestedBranchId = $request->integer('branch_id') ?: null;

        if ($requestedBranchId !== null && ! in_array($requestedBranchId, $branchIds, true)) {
            abort(403, 'Cabang tidak berada dalam cakupan akses pengguna.');
        }

        $branchId = $requestedBranchId;
        if ($branchId === null && ! $actor->hasCompanyScopedRole()) {
            $currentBranchId = (int) ($actor->current_branch_id ?? 0);
            $branchId = in_array($currentBranchId, $branchIds, true)
                ? $currentBranchId
                : null;
        }

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->string('from')->toString())->startOfDay()
            : now()->toImmutable()->startOfMonth()->startOfDay();
        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->string('to')->toString())->endOfDay()
            : now()->toImmutable()->endOfDay();

        if ($to->lessThan($from)) {
            throw ValidationException::withMessages([
                'to' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
            ]);
        }

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages([
                'to' => 'Rentang Finance Dashboard maksimal 367 hari.',
            ]);
        }

        $filters = [
            'from' => $from,
            'to' => $to,
            'branch_id' => $branchId,
        ];

        return Inertia::render('finance/dashboard', [
            ...$dashboard->summarize(
                (int) $actor->company_id,
                $branchIds,
                $filters,
            ),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'branch_id' => $branchId,
            ],
            'branches' => $branches,
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
