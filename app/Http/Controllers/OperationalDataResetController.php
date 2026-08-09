<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Access\UserAccessManager;
use App\Domain\Operations\OperationalDataResetService;
use App\Http\Requests\ResetOperationalDataRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationalDataResetController extends Controller
{
    public function index(
        Request $request,
        UserAccessManager $accessManager,
        OperationalDataResetService $resetService,
    ): Response {
        $user = $this->guard($request, $accessManager);
        $scope = (string) $request->query('scope', (string) ($user->current_branch_id ?? 'all'));
        $branch = $this->resolveBranch($user, $scope);

        return Inertia::render('operations/reset', [
            'branches' => Branch::query()
                ->where('company_id', $user->company_id)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'city', 'is_active']),
            'selectedScope' => $branch === null ? 'all' : (string) $branch->id,
            'scopeLabel' => $branch === null
                ? 'Semua cabang perusahaan'
                : "{$branch->code} · {$branch->name}",
            'confirmationPhrase' => $branch === null
                ? 'RESET SEMUA CABANG'
                : 'RESET '.mb_strtoupper($branch->code),
            'summary' => $resetService->preview((int) $user->company_id, $branch),
            'environment' => app()->environment(),
        ]);
    }

    public function store(
        ResetOperationalDataRequest $request,
        UserAccessManager $accessManager,
        OperationalDataResetService $resetService,
        ActivityRecorder $activityRecorder,
    ): RedirectResponse {
        $user = $this->guard($request, $accessManager);
        $branch = $this->resolveBranch($user, (string) $request->validated('scope'));
        $result = $resetService->reset(
            (int) $user->company_id,
            $branch,
            (bool) $request->validated('normalize_condition'),
        );

        $subject = $branch ?? Company::query()->findOrFail($user->company_id);
        $activityRecorder->record(
            $request,
            'operations.reset',
            $subject,
            null,
            [
                'scope' => $branch === null ? 'all' : $branch->code,
                'normalize_condition' => (bool) $request->validated('normalize_condition'),
                'deleted' => $result,
            ],
            $branch?->id,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data operasional berhasil di-reset.',
            'description' => $branch === null
                ? 'Seluruh cabang kembali ke baseline operasional tanpa menghapus master data.'
                : "Cabang {$branch->code} kembali ke baseline operasional tanpa menghapus master data.",
            'duration' => 7000,
        ]);

        return redirect()->route('operations.reset.index', [
            'scope' => $branch === null ? 'all' : $branch->id,
        ]);
    }

    private function guard(Request $request, UserAccessManager $accessManager): User
    {
        abort_unless(
            app()->environment(['local', 'testing', 'staging']),
            403,
            'Reset data operasional hanya tersedia pada environment local, testing, atau staging.',
        );

        $user = $request->user();
        abort_unless($user instanceof User && $accessManager->isSuperAdministrator($user), 403);
        abort_unless($user->company_id !== null, 403);

        return $user;
    }

    private function resolveBranch(User $user, string $scope): ?Branch
    {
        if ($scope === 'all') {
            return null;
        }

        abort_unless(ctype_digit($scope), 404);

        return Branch::query()
            ->where('company_id', $user->company_id)
            ->whereKey((int) $scope)
            ->firstOrFail();
    }
}
