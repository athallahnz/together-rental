<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Http\Requests\SavePromotionRequest;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('products.view');
        $user = $request->user();
        $branches = $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']);
        $branchIds = $branches->pluck('id');
        $branchId = $request->integer('branch_id') ?: null;
        $status = $request->string('status')->toString();
        $search = trim($request->string('search')->toString());

        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }

        $promotions = Promotion::query()
            ->where('company_id', $user->company_id)
            ->where(fn (Builder $query) => $query
                ->whereNull('branch_id')
                ->orWhereIn('branch_id', $branchIds))
            ->when($branchId !== null, fn (Builder $query) => $query
                ->where(fn (Builder $scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId)))
            ->when($status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")))
            ->with('branch:id,code,name')
            ->withCount([
                'bookings as booking_usage_count' => fn (Builder $query) => $query
                    ->whereNotIn('status', ['cancelled', 'expired']),
                'extensions as extension_usage_count' => fn (Builder $query) => $query
                    ->whereIn('status', ['approved', 'completed']),
            ])
            ->orderByDesc('is_active')
            ->orderBy('code')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Promotion $promotion) use ($user): Promotion {
                $promotion->setAttribute(
                    'can_manage',
                    $user->hasCompanyScopedRole()
                        || ($promotion->branch_id !== null
                            && $promotion->branch_id === $user->current_branch_id),
                );

                return $promotion;
            });

        $defaultBranchId = $user->hasCompanyScopedRole()
            ? null
            : $user->current_branch_id;

        return Inertia::render('catalog/promotions', [
            'promotions' => $promotions,
            'branches' => $branches,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'branch_id' => $branchId,
            ],
            'canManage' => $user->can('products.manage'),
            'canCreateGlobal' => $user->hasCompanyScopedRole(),
            'defaultBranchId' => $defaultBranchId,
        ]);
    }

    public function store(
        SavePromotionRequest $request,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $promotion = Promotion::query()->create($this->payload($request));
        $recorder->record(
            $request,
            'promotion.created',
            $promotion,
            null,
            $this->audit($promotion),
            $promotion->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Promo {$promotion->code} berhasil dibuat.",
        ]);
    }

    public function update(
        SavePromotionRequest $request,
        Promotion $promotion,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $promotion, true);
        $before = $this->audit($promotion);
        $promotion->update($this->payload($request));
        $recorder->record(
            $request,
            'promotion.updated',
            $promotion,
            $before,
            $this->audit($promotion),
            $promotion->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Promo {$promotion->code} berhasil diperbarui.",
        ]);
    }

    public function archive(
        Request $request,
        Promotion $promotion,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('products.manage');
        $this->guardAccess($request, $promotion, true);
        $before = $this->audit($promotion);
        $promotion->update(['is_active' => false]);
        $recorder->record(
            $request,
            'promotion.archived',
            $promotion,
            $before,
            $this->audit($promotion),
            $promotion->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Promo {$promotion->code} dinonaktifkan tanpa mengubah transaksi lama.",
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(SavePromotionRequest $request): array
    {
        $validated = $request->validated();

        return [
            'company_id' => $request->user()->company_id,
            'branch_id' => $validated['branch_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'value' => $validated['type'] === 'bonus_duration' ? 0 : ($validated['value'] ?? 0),
            'maximum_discount' => $validated['type'] === 'percentage'
                ? ($validated['maximum_discount'] ?? null)
                : null,
            'minimum_transaction' => $validated['minimum_transaction'] ?? 0,
            'bonus_duration' => $validated['type'] === 'bonus_duration'
                ? ($validated['bonus_duration'] ?? 0)
                : 0,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'is_active' => $validated['is_active'],
            'rules' => [
                'member_only' => (bool) ($validated['member_only'] ?? false),
                'non_member_only' => (bool) ($validated['non_member_only'] ?? false),
                'allow_member_stack' => (bool) ($validated['allow_member_stack'] ?? false),
            ],
        ];
    }

    private function guardAccess(Request $request, Promotion $promotion, bool $manage): void
    {
        abort_unless($promotion->company_id === $request->user()->company_id, 404);
        if ($promotion->branch_id !== null) {
            abort_unless(
                $request->user()->accessibleBranches()->whereKey($promotion->branch_id)->exists(),
                404,
            );
        }
        if ($manage && $promotion->branch_id === null && ! $request->user()->hasCompanyScopedRole()) {
            abort(403, 'Promo global hanya dapat dikelola oleh role company.');
        }
        if ($manage && $promotion->branch_id !== null && ! $request->user()->hasCompanyScopedRole()) {
            abort_unless($promotion->branch_id === $request->user()->current_branch_id, 403);
        }
    }

    /** @return array<string, mixed> */
    private function audit(Promotion $promotion): array
    {
        return $promotion->only([
            'id', 'company_id', 'branch_id', 'code', 'name', 'type', 'value',
            'maximum_discount', 'minimum_transaction', 'bonus_duration', 'usage_limit',
            'starts_at', 'ends_at', 'is_active', 'rules',
        ]);
    }
}
