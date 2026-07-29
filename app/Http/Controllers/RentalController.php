<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Rentals\RentalFinancialCorrectionManager;
use App\Domain\Rentals\RentalManager;
use App\Domain\Rentals\RentalReturnManager;
use App\Http\Requests\CheckoutBookingRequest;
use App\Http\Requests\StoreDirectRentalRequest;
use App\Http\Requests\StoreRentalFinancialAdjustmentRequest;
use App\Http\Requests\StoreRentalReturnRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\RentalPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RentalController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('rentals.view');
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $base = Rental::query()
            ->whereIn('branch_id', $user->accessibleBranches()->select('id'));

        $rentals = (clone $base)
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $nested) => $nested
                    ->where('rental_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$search}%")),
            ))
            ->when(in_array($status, ['active', 'partial_return', 'returned', 'completed'], true),
                fn (Builder $query) => $query->where('status', $status))
            ->when($branchId !== null,
                fn (Builder $query) => $query->where('branch_id', $branchId))
            ->with([
                'branch:id,code,name',
                'customer:id,customer_number,name,phone',
                'booking:id,booking_number,source',
            ])
            ->withCount('items')
            ->orderByDesc('checked_out_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('rentals/index', [
            'rentals' => $rentals,
            'summary' => [
                'active' => (clone $base)->where('status', 'active')->count(),
                'overdue' => (clone $base)->whereIn('status', ['active', 'partial_return'])
                    ->where('due_at', '<', now())->count(),
                'today' => (clone $base)->whereDate('checked_out_at', today())->count(),
            ],
            'filters' => compact('search', 'status', 'branchId'),
            'branches' => $user->accessibleBranches()
                ->orderBy('name')->get(['id', 'code', 'name']),
            'permissions' => $this->permissions($user),
        ]);
    }

    public function createDirect(Request $request): Response
    {
        Gate::authorize('rentals.create');

        return Inertia::render('bookings/form', [
            ...$this->formOptions($request),
            'mode' => 'direct',
            'paymentMethods' => $this->paymentMethods($request->user()),
        ]);
    }

    public function storeDirect(
        StoreDirectRentalRequest $request,
        RentalManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $rental = $manager->createDirect($request->validated(), $request->user());
        $recorder->record(
            $request,
            'rental.direct_checked_out',
            $rental,
            null,
            $this->audit($rental),
            $rental->branch_id,
        );

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => "Rental langsung {$rental->rental_number} berhasil di-checkout.",
        ]);
    }

    public function createCheckout(Request $request, Booking $booking): Response
    {
        Gate::authorize('rentals.create');
        $this->guardBookingAccess($request, $booking);
        abort_unless($booking->status === 'confirmed' && ! $booking->rental()->exists(), 409);
        $booking->load([
            'branch:id,code,name',
            'customer:id,customer_number,name,phone',
            'ratePlan:id,code,name,duration_unit,duration_value',
            'items.reservations.asset:id,product_id,asset_code,serial_number,status,condition',
        ]);

        return Inertia::render('rentals/checkout', [
            'booking' => $booking,
            'paymentMethods' => $this->paymentMethods($request->user()),
        ]);
    }

    public function storeCheckout(
        CheckoutBookingRequest $request,
        Booking $booking,
        RentalManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardBookingAccess($request, $booking);
        $rental = $manager->checkout($booking, $request->validated(), $request->user());
        $recorder->record(
            $request,
            'rental.booking_checked_out',
            $rental,
            ['booking_status' => 'confirmed'],
            $this->audit($rental),
            $rental->branch_id,
        );

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => "Booking berhasil di-checkout menjadi {$rental->rental_number}.",
        ]);
    }

    public function show(Request $request, Rental $rental): Response
    {
        Gate::authorize('rentals.view');
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($rental->branch_id)->exists(),
            404,
        );
        $rental->load([
            'branch:id,code,name',
            'customer:id,customer_number,name,phone,email,risk_level',
            'booking:id,booking_number,source',
            'ratePlan:id,code,name,duration_unit,duration_value',
            'items.product:id,sku,name',
            'items.assets.asset:id,asset_code,serial_number,status,condition',
            'returns:id,rental_id,return_number,type,status,returned_at,total_charge_amount',
            'statusHistories.changer:id,name',
            'payments:id,rental_id,type,amount,status,paid_at,external_reference',
            'financialAdjustments' => fn ($query) => $query->latest(),
            'financialAdjustments.creator:id,name',
        ]);

        return Inertia::render('rentals/show', [
            'rental' => $rental,
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function createReturn(Request $request, Rental $rental): Response
    {
        Gate::authorize('rentals.return');
        $this->guardRentalAccess($request, $rental);
        abort_unless(in_array($rental->status, ['active', 'partial_return'], true), 409);
        $rental->load([
            'branch:id,code,name',
            'customer:id,customer_number,name,phone',
            'items' => fn ($query) => $query->whereIn('status', ['out', 'partial_return']),
            'items.assets' => fn ($query) => $query->where('status', 'out'),
            'items.assets.asset:id,asset_code,serial_number,status,condition',
        ]);

        return Inertia::render('rentals/return', [
            'rental' => $rental,
            'paymentMethods' => $this->paymentMethods($request->user()),
        ]);
    }

    public function storeReturn(
        StoreRentalReturnRequest $request,
        Rental $rental,
        RentalReturnManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardRentalAccess($request, $rental);
        $return = $manager->process($rental, $request->validated(), $request->user());
        $recorder->record(
            $request,
            'rental.return_completed',
            $rental->fresh(),
            ['status' => $rental->status],
            [
                'return_id' => $return->id,
                'return_number' => $return->return_number,
                'type' => $return->type,
                'total_charge_amount' => $return->total_charge_amount,
            ],
            $rental->branch_id,
        );

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => "Pengembalian {$return->return_number} berhasil diproses.",
        ]);
    }

    public function storeFinancialCorrection(
        StoreRentalFinancialAdjustmentRequest $request,
        Rental $rental,
        RentalFinancialCorrectionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardRentalAccess($request, $rental);

        $adjustment = DB::transaction(function () use (
            $request,
            $rental,
            $manager,
            $recorder,
        ) {
            $before = $this->audit($rental);
            $adjustment = $manager->create(
                $rental,
                $request->validated(),
                $request->user(),
            );
            $fresh = $rental->fresh();
            $recorder->record(
                $request,
                'rental.completed_financial_corrected',
                $fresh,
                $before,
                [
                    ...$this->audit($fresh),
                    'adjustment_id' => $adjustment->id,
                    'adjustment_number' => $adjustment->adjustment_number,
                    'component' => $adjustment->component,
                    'direction' => $adjustment->direction,
                    'amount' => $adjustment->amount,
                    'reason' => $adjustment->reason,
                ],
                $rental->branch_id,
            );

            return $adjustment;
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Koreksi {$adjustment->adjustment_number} berhasil dicatat.",
        ]);
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $user = $request->user();
        $branchIds = $user->accessibleBranches()->pluck('id');

        return [
            'booking' => null,
            'branches' => $user->accessibleBranches()
                ->orderBy('name')->get(['id', 'code', 'name']),
            'customers' => Customer::query()->whereRaw('1 = 0')
                ->get(['id', 'customer_number', 'name', 'phone']),
            'ratePlans' => RatePlan::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where(fn (Builder $query) => $query
                    ->whereNull('branch_id')
                    ->orWhereIn('branch_id', $branchIds))
                ->orderBy('name')
                ->get(['id', 'branch_id', 'code', 'name', 'duration_unit', 'duration_value']),
            'products' => Product::query()->whereRaw('1 = 0')->get(['id', 'sku', 'name']),
            'packages' => RentalPackage::query()->whereRaw('1 = 0')
                ->get(['id', 'branch_id', 'code', 'name']),
        ];
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

    private function guardBookingAccess(Request $request, Booking $booking): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($booking->branch_id)->exists(),
            404,
        );
    }

    private function guardRentalAccess(Request $request, Rental $rental): void
    {
        abort_unless(
            $request->user()->accessibleBranches()->whereKey($rental->branch_id)->exists(),
            404,
        );
    }

    /** @return array<string, bool> */
    private function permissions(User $user): array
    {
        return [
            'create' => $user->can('rentals.create'),
            'update' => $user->can('rentals.update'),
            'return' => $user->can('rentals.return'),
            'correctCompleted' => $user->can('rentals.correct_completed'),
        ];
    }

    /** @return array<string, mixed> */
    private function audit(Rental $rental): array
    {
        return $rental->only([
            'id', 'rental_number', 'branch_id', 'booking_id', 'customer_id',
            'status', 'checked_out_at', 'due_at', 'total_amount', 'paid_amount',
            'deposit_amount', 'balance_due',
        ]);
    }
}
