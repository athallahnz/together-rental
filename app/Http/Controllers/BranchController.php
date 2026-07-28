<?php

namespace App\Http\Controllers;

use App\Domain\Branches\BranchProvisioner;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('branches.view');

        $user = $request->user();
        $query = Branch::query()
            ->where('company_id', $user->company_id);

        if (! $user->can('branches.manage') && ! $user->hasCompanyScopedRole()) {
            $query->whereIn('id', $user->accessibleBranches()->select('id'));
        }

        $scope = clone $query;
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $branches = $query
            ->when($search !== '', function (Builder $branchQuery) use ($search): void {
                $branchQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when(
                in_array($status, ['active', 'inactive'], true),
                fn (Builder $branchQuery) => $branchQuery->where(
                    'is_active',
                    $status === 'active',
                ),
            )
            ->withCount([
                'users as active_users_count' => fn ($userQuery) => $userQuery
                    ->where('branch_user.is_active', true),
                'employees as active_employees_count' => fn ($employeeQuery) => $employeeQuery
                    ->where('status', 'active'),
            ])
            ->addSelect([
                'customers_count' => DB::table('customers')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('customers.registered_branch_id', 'branches.id')
                    ->whereNull('customers.deleted_at'),
                'assets_count' => DB::table('assets')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('assets.current_branch_id', 'branches.id')
                    ->whereNull('assets.deleted_at'),
                'rentals_count' => DB::table('rentals')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('rentals.branch_id', 'branches.id')
                    ->whereNull('rentals.deleted_at'),
                'active_rentals_count' => DB::table('rentals')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('rentals.branch_id', 'branches.id')
                    ->whereNull('rentals.deleted_at')
                    ->whereNotIn('rentals.status', [
                        'returned',
                        'cancelled',
                        'completed',
                    ]),
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $publicSettings = DB::table('branch_settings')
            ->whereIn('branch_id', $branches->pluck('id'))
            ->where('key', 'public_catalog_enabled')
            ->pluck('value', 'branch_id');
        $publicScopeBranchIds = (clone $scope)
            ->where('is_active', true)
            ->pluck('id');
        $publicCount = DB::table('branch_settings')
            ->whereIn('branch_id', $publicScopeBranchIds)
            ->where('key', 'public_catalog_enabled')
            ->pluck('value')
            ->filter(function (mixed $value): bool {
                $decoded = is_string($value) ? json_decode($value, true) : $value;

                return is_bool($decoded)
                    ? $decoded
                    : in_array(mb_strtolower((string) $decoded), ['1', 'true', 'yes', 'on'], true);
            })
            ->count();

        $branches->each(function (Branch $branch) use ($publicSettings): void {
            $value = $publicSettings->get($branch->id);
            $decoded = is_string($value) ? json_decode($value, true) : $value;

            $branch->setAttribute(
                'public_catalog_enabled',
                is_bool($decoded)
                    ? $decoded
                    : in_array(mb_strtolower((string) $decoded), ['1', 'true', 'yes', 'on'], true),
            );
        });

        return Inertia::render('branches/index', [
            'branches' => $branches,
            'summary' => [
                'total' => (clone $scope)->count(),
                'active' => (clone $scope)->where('is_active', true)->count(),
                'inactive' => (clone $scope)->where('is_active', false)->count(),
                'public' => $publicCount,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'permissions' => [
                'manage' => $user->can('branches.manage'),
                'switch' => $user->can('branches.switch'),
            ],
        ]);
    }

    public function store(
        StoreBranchRequest $request,
        BranchProvisioner $provisioner,
    ): RedirectResponse {
        $user = $request->user();

        $branch = DB::transaction(function () use ($request, $provisioner, $user): Branch {
            $branch = Branch::query()->create([
                ...$request->validated(),
                'company_id' => $user->company_id,
            ]);

            $provisioner->provision($branch);
            $user->branches()->syncWithoutDetaching([
                $branch->id => [
                    'is_default' => false,
                    'is_active' => true,
                ],
            ]);

            $this->recordActivity($request, $branch, 'branch.created', null, $branch->toArray());

            return $branch;
        });

        return to_route('branches.index')->with('toast', [
            'type' => 'success',
            'message' => "Cabang {$branch->code} berhasil dibuat dan diprovisikan.",
        ]);
    }

    public function update(
        UpdateBranchRequest $request,
        Branch $branch,
        BranchProvisioner $provisioner,
    ): RedirectResponse {
        $this->guardCompany($request, $branch);
        $validated = $request->validated();
        $oldValues = $branch->toArray();
        $codeChanged = $branch->code !== $validated['code'];

        if ($codeChanged && $this->hasOperationalData($branch)) {
            throw ValidationException::withMessages([
                'code' => 'Kode cabang tidak dapat diubah karena cabang sudah memiliki data operasional.',
            ]);
        }

        DB::transaction(function () use (
            $branch,
            $codeChanged,
            $provisioner,
            $request,
            $oldValues,
            $validated,
        ): void {
            $branch->update($validated);

            if ($codeChanged) {
                $provisioner->syncNumberSequences($branch);
            }

            DB::table('branch_settings')
                ->where('branch_id', $branch->id)
                ->where('key', 'default_timezone')
                ->update([
                    'value' => json_encode($branch->timezone, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

            $this->recordActivity(
                $request,
                $branch,
                'branch.updated',
                $oldValues,
                $branch->fresh()->toArray(),
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Data cabang {$branch->code} berhasil diperbarui.",
        ]);
    }

    public function toggleStatus(Request $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('branches.manage');
        $this->guardCompany($request, $branch);
        $activate = ! $branch->is_active;

        if (! $activate) {
            $this->guardDeactivation($request, $branch);
        }

        $oldValues = ['is_active' => $branch->is_active];
        DB::transaction(function () use ($activate, $branch, $oldValues, $request): void {
            $branch->update(['is_active' => $activate]);

            $this->recordActivity(
                $request,
                $branch,
                $activate ? 'branch.activated' : 'branch.deactivated',
                $oldValues,
                ['is_active' => $activate],
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Cabang {$branch->code} berhasil ".
                ($activate ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }

    public function switch(Request $request, Branch $branch): RedirectResponse
    {
        Gate::authorize('branches.switch');
        $this->guardCompany($request, $branch);
        $user = $request->user();

        if (! $user->canAccessBranch($branch)) {
            abort(403, 'Anda tidak memiliki akses aktif ke cabang tersebut.');
        }

        $oldBranchId = $user->current_branch_id;
        DB::transaction(function () use ($branch, $oldBranchId, $request, $user): void {
            $user->update(['current_branch_id' => $branch->id]);

            $this->recordActivity(
                $request,
                $branch,
                'branch.switched',
                ['current_branch_id' => $oldBranchId],
                ['current_branch_id' => $branch->id],
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Cabang aktif diubah ke {$branch->code} · {$branch->name}.",
        ]);
    }

    private function guardCompany(Request $request, Branch $branch): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $branch->company_id === $request->user()->company_id,
            404,
        );
    }

    private function guardDeactivation(Request $request, Branch $branch): void
    {
        if ($request->user()->current_branch_id === $branch->id) {
            throw ValidationException::withMessages([
                'branch' => 'Pindahkan cabang aktif Anda sebelum menonaktifkan cabang ini.',
            ]);
        }

        $activeBranches = Branch::query()
            ->where('company_id', $branch->company_id)
            ->where('is_active', true)
            ->count();

        if ($activeBranches <= 1) {
            throw ValidationException::withMessages([
                'branch' => 'Perusahaan wajib memiliki minimal satu cabang aktif.',
            ]);
        }

        $blockers = [
            'rental aktif' => DB::table('rentals')
                ->where('branch_id', $branch->id)
                ->whereNotIn('status', ['returned', 'cancelled', 'completed'])
                ->exists(),
            'booking aktif' => DB::table('bookings')
                ->where('branch_id', $branch->id)
                ->whereNotIn('status', ['cancelled', 'converted', 'completed', 'expired'])
                ->exists(),
            'sesi kas terbuka' => DB::table('cash_sessions')
                ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
                ->where('cash_registers.branch_id', $branch->id)
                ->where('cash_sessions.status', 'open')
                ->exists(),
            'transfer antar cabang berjalan' => DB::table('branch_transfers')
                ->where(function ($query) use ($branch): void {
                    $query
                        ->where('from_branch_id', $branch->id)
                        ->orWhere('to_branch_id', $branch->id);
                })
                ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
                ->exists(),
        ];

        $activeBlockers = array_keys(array_filter($blockers));

        if ($activeBlockers !== []) {
            throw ValidationException::withMessages([
                'branch' => 'Cabang belum dapat dinonaktifkan karena masih memiliki '.
                    implode(', ', $activeBlockers).'.',
            ]);
        }
    }

    private function hasOperationalData(Branch $branch): bool
    {
        foreach ([
            ['customers', 'registered_branch_id'],
            ['assets', 'current_branch_id'],
            ['bookings', 'branch_id'],
            ['rentals', 'branch_id'],
            ['payments', 'branch_id'],
            ['legacy_import_batches', 'branch_id'],
        ] as [$table, $column]) {
            if (DB::table($table)->where($column, $branch->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    private function recordActivity(
        Request $request,
        Branch $branch,
        string $event,
        ?array $oldValues,
        array $newValues,
    ): void {
        DB::table('activity_logs')->insert([
            'company_id' => $branch->company_id,
            'branch_id' => $branch->id,
            'actor_id' => $request->user()->id,
            'subject_type' => Branch::class,
            'subject_id' => $branch->id,
            'event' => $event,
            'description' => "{$branch->code} · {$branch->name}",
            'old_values' => $oldValues === null
                ? null
                : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => json_encode($newValues, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id') === null
                ? null
                : mb_substr((string) $request->header('X-Request-Id'), 0, 64),
            'created_at' => now(),
        ]);
    }
}
