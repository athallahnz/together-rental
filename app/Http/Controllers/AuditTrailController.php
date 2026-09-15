<?php

namespace App\Http\Controllers;

use App\Domain\Audit\AuditTrailPresenter;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailController extends Controller
{
    public function index(Request $request, AuditTrailPresenter $presenter): Response
    {
        Gate::authorize('audit.view');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'actor_id' => ['nullable', 'integer'],
            'module' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9_-]+$/'],
            'event' => ['nullable', 'string', 'max:80'],
            'request_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => [
                'nullable',
                'date',
                Rule::when($request->filled('date_from'), ['after_or_equal:date_from']),
            ],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $branches = $this->branches($actor);
        $allowedBranchIds = $branches->pluck('id')->map(fn ($id): int => (int) $id);
        $companyScoped = $actor->hasCompanyScopedRole();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId !== null && ! $allowedBranchIds->contains($branchId)) {
            abort(403);
        }

        $base = $this->baseScope($actor, $allowedBranchIds, $companyScoped);
        $events = (clone $base)
            ->whereNotNull('activity_logs.event')
            ->distinct()
            ->orderBy('activity_logs.event')
            ->pluck('activity_logs.event')
            ->map(fn ($event): array => [
                'value' => (string) $event,
                'label' => str((string) $event)->replace(['.', '_', '-'], ' ')->squish()->title()->toString(),
                'module' => $presenter->module((string) $event),
            ])
            ->values();
        $modules = $events
            ->map(fn (array $event): string => $event['module'])
            ->unique()
            ->sort()
            ->values()
            ->map(fn (string $module): array => [
                'value' => $module,
                'label' => str($module)->replace(['_', '-'], ' ')->title()->toString(),
            ]);
        $actors = $this->actors($base);

        $scope = $this->applyFilters(clone $base, $validated);
        $summary = [
            'total' => (clone $scope)->count(),
            'today' => (clone $scope)->whereDate('activity_logs.created_at', today())->count(),
            'actors' => (clone $scope)->whereNotNull('activity_logs.actor_id')->distinct()->count('activity_logs.actor_id'),
            'with_changes' => (clone $scope)
                ->where(function (Builder $query): void {
                    $query->whereNotNull('activity_logs.old_values')
                        ->orWhereNotNull('activity_logs.new_values');
                })
                ->count(),
        ];

        $activities = $scope
            ->select([
                'activity_logs.id',
                'activity_logs.branch_id',
                'activity_logs.actor_id',
                'activity_logs.subject_type',
                'activity_logs.subject_id',
                'activity_logs.event',
                'activity_logs.description',
                'activity_logs.old_values',
                'activity_logs.new_values',
                'activity_logs.ip_address',
                'activity_logs.user_agent',
                'activity_logs.request_id',
                'activity_logs.created_at',
                'actors.name as actor_name',
                'actors.email as actor_email',
                'branches.code as branch_code',
                'branches.name as branch_name',
            ])
            ->orderByDesc('activity_logs.created_at')
            ->orderByDesc('activity_logs.id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn ($row): array => $presenter->present($row, $actor));

        return Inertia::render('audit/index', [
            'activities' => $activities,
            'summary' => $summary,
            'branches' => $branches->map(fn (Branch $branch): array => [
                'id' => $branch->id,
                'code' => $branch->code,
                'name' => $branch->name,
                'is_active' => (bool) $branch->is_active,
            ])->values(),
            'actors' => $actors,
            'modules' => $modules,
            'events' => $events,
            'filters' => [
                'search' => (string) ($validated['search'] ?? ''),
                'branch_id' => $branchId,
                'actor_id' => isset($validated['actor_id']) ? (int) $validated['actor_id'] : null,
                'module' => (string) ($validated['module'] ?? ''),
                'event' => (string) ($validated['event'] ?? ''),
                'request_id' => (string) ($validated['request_id'] ?? ''),
                'date_from' => (string) ($validated['date_from'] ?? ''),
                'date_to' => (string) ($validated['date_to'] ?? ''),
            ],
        ]);
    }

    /** @return Collection<int, Branch> */
    private function branches(User $actor): Collection
    {
        if ($actor->company_id === null) {
            return collect();
        }

        if ($actor->hasCompanyScopedRole()) {
            return Branch::query()
                ->where('company_id', $actor->company_id)
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'is_active']);
        }

        return $actor->accessibleBranches()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'is_active']);
    }

    /** @param Collection<int, int> $allowedBranchIds */
    private function baseScope(User $actor, Collection $allowedBranchIds, bool $companyScoped): Builder
    {
        $query = DB::table('activity_logs')
            ->leftJoin('users as actors', 'actors.id', '=', 'activity_logs.actor_id')
            ->leftJoin('branches', 'branches.id', '=', 'activity_logs.branch_id')
            ->where('activity_logs.company_id', $actor->company_id);

        if (! $companyScoped) {
            $query->whereIn('activity_logs.branch_id', $allowedBranchIds->all());
        }

        return $query;
    }

    /** @return Collection<int, array{id: int, name: string, email: string}> */
    private function actors(Builder $base): Collection
    {
        return (clone $base)
            ->whereNotNull('activity_logs.actor_id')
            ->select(['actors.id', 'actors.name', 'actors.email'])
            ->distinct()
            ->orderBy('actors.name')
            ->get()
            ->map(fn ($actor): array => [
                'id' => (int) $actor->id,
                'name' => (string) $actor->name,
                'email' => (string) $actor->email,
            ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $module = trim((string) ($filters['module'] ?? ''));
        $event = trim((string) ($filters['event'] ?? ''));
        $requestId = trim((string) ($filters['request_id'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $needle = "%{$search}%";
                $nested->where('activity_logs.event', 'like', $needle)
                    ->orWhere('activity_logs.description', 'like', $needle)
                    ->orWhere('activity_logs.subject_type', 'like', $needle)
                    ->orWhere('activity_logs.request_id', 'like', $needle)
                    ->orWhere('actors.name', 'like', $needle)
                    ->orWhere('actors.email', 'like', $needle)
                    ->orWhere('branches.code', 'like', $needle)
                    ->orWhere('branches.name', 'like', $needle);
            });
        }

        if (isset($filters['branch_id'])) {
            $query->where('activity_logs.branch_id', (int) $filters['branch_id']);
        }
        if (isset($filters['actor_id'])) {
            $query->where('activity_logs.actor_id', (int) $filters['actor_id']);
        }
        if ($module !== '') {
            $query->where('activity_logs.event', 'like', $module.'.%');
        }
        if ($event !== '') {
            $query->where('activity_logs.event', $event);
        }
        if ($requestId !== '') {
            $query->where('activity_logs.request_id', 'like', "%{$requestId}%");
        }
        if (! empty($filters['date_from'])) {
            $query->where('activity_logs.created_at', '>=', Carbon::parse((string) $filters['date_from'])->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('activity_logs.created_at', '<=', Carbon::parse((string) $filters['date_to'])->endOfDay());
        }

        return $query;
    }
}
