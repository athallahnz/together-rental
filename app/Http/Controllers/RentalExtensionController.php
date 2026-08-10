<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Rentals\RentalExtensionManager;
use App\Http\Requests\StoreRentalExtensionRequest;
use App\Models\PaymentMethod;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RentalExtensionController extends Controller
{
    public function create(
        Request $request,
        Rental $rental,
        RentalExtensionManager $manager,
    ): Response {
        Gate::authorize('rentals.extend');
        $this->guardAccess($request, $rental);
        abort_unless(in_array($rental->status, ['active', 'partial_return'], true), 409);
        $rental->load([
            'branch:id,code,name',
            'customer:id,customer_number,name,phone,is_member,member_number,member_since',
            'ratePlan:id,code,name,duration_unit,duration_value',
        ]);

        return Inertia::render('rentals/extend', [
            'rental' => $rental,
            'items' => $manager->quoteOptions($rental),
            'paymentMethods' => $this->paymentMethods($request->user()),
            'cashSessions' => $this->cashSessions($request->user(), $rental->branch_id),
        ]);
    }

    public function store(
        StoreRentalExtensionRequest $request,
        Rental $rental,
        RentalExtensionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $rental);
        $before = $rental->only([
            'due_at', 'subtotal', 'total_amount', 'paid_amount', 'balance_due',
        ]);
        $extension = $manager->extend($rental, $request->validated(), $request->user());
        $fresh = $rental->fresh();
        $pricingSnapshot = $extension->getAttribute('pricing_snapshot');
        $promotionSnapshot = is_array($pricingSnapshot)
            && isset($pricingSnapshot['promotion'])
            && is_array($pricingSnapshot['promotion'])
                ? $pricingSnapshot['promotion']
                : [];
        $promotionCode = isset($promotionSnapshot['code'])
            && is_string($promotionSnapshot['code'])
                ? $promotionSnapshot['code']
                : null;
        $recorder->record(
            $request,
            'rental.extended',
            $fresh,
            $before,
            [
                ...$fresh->only([
                    'due_at', 'subtotal', 'total_amount', 'paid_amount', 'balance_due',
                ]),
                'extension_id' => $extension->id,
                'extension_number' => $extension->extension_number,
                'previous_due_at' => $extension->previous_due_at,
                'extended_due_at' => $extension->extended_due_at,
                'extension_total' => $extension->total_amount,
                'extension_discount' => $extension->discount_amount,
                'extension_paid' => $extension->paid_amount,
                'promotion_id' => $extension->promotion_id,
                'promotion_code' => $promotionCode,
                'item_ids' => $extension->items->pluck('rental_item_id')->all(),
            ],
            $rental->branch_id,
        );

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => "Perpanjangan {$extension->extension_number} berhasil disetujui.",
        ]);
    }

    /** @return Collection<int, PaymentMethod> */
    private function paymentMethods(User $user): Collection
    {
        return PaymentMethod::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type', 'requires_reference']);
    }

    /** @return Collection<int, \stdClass> */
    private function cashSessions(User $user, int $branchId): Collection
    {
        $branchIds = $user->accessibleBranches()->pluck('id');

        return DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->where('cash_sessions.status', 'open')
            ->whereIn('cash_registers.branch_id', $branchIds)
            ->where('cash_registers.branch_id', $branchId)
            ->orderBy('cash_registers.name')
            ->get([
                'cash_sessions.id',
                'cash_registers.branch_id',
                'cash_registers.name as register_name',
                'cash_sessions.opened_at',
            ]);
    }

    private function guardAccess(Request $request, Rental $rental): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($rental->branch_id)->exists(),
            404,
        );
    }
}
