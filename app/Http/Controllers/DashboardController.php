<?php

namespace App\Http\Controllers;

use App\Domain\Dashboard\OperationalDashboardService;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        OperationalDashboardService $dashboard,
    ): Response {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $branches = $actor->accessibleBranches()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'city']);

        /** @var list<int> $accessibleBranchIds */
        $accessibleBranchIds = array_values($branches->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all());
        $requestedBranchId = $request->integer('branch_id') ?: null;

        if ($requestedBranchId !== null && ! in_array($requestedBranchId, $accessibleBranchIds, true)) {
            abort(403, 'Cabang tidak berada dalam cakupan akses pengguna.');
        }

        $branchId = $requestedBranchId;
        if ($branchId === null && ! $actor->hasCompanyScopedRole()) {
            $currentBranchId = (int) ($actor->current_branch_id ?? 0);
            $branchId = in_array($currentBranchId, $accessibleBranchIds, true)
                ? $currentBranchId
                : ($accessibleBranchIds[0] ?? null);
        }

        $scopedBranchIds = $branchId === null
            ? $accessibleBranchIds
            : [$branchId];
        $selectedBranch = $branchId === null
            ? null
            : $branches->firstWhere('id', $branchId);

        return Inertia::render('dashboard', [
            ...$dashboard->summarize($actor, $scopedBranchIds),
            'branches' => $branches,
            'filters' => [
                'branch_id' => $branchId,
            ],
            'scope' => [
                'is_company_scope' => $branchId === null,
                'allow_all_branches' => $actor->hasCompanyScopedRole(),
                'label' => $selectedBranch === null
                    ? 'Seluruh cabang yang dapat diakses'
                    : $selectedBranch->code.' · '.$selectedBranch->name,
            ],
            'generatedAt' => now()->toIso8601String(),
        ]);
    }
}
