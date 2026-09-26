<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Finance\BookingPaymentSettlement;
use App\Domain\Rentals\RentalCollateralDocumentStorage;
use App\Domain\Rentals\RentalFinancialCorrectionManager;
use App\Domain\Rentals\RentalManager;
use App\Domain\Rentals\RentalOperationalCorrectionManager;
use App\Domain\Rentals\RentalOvertimeCalculator;
use App\Domain\Rentals\RentalReturnManager;
use App\Http\Requests\CheckoutBookingRequest;
use App\Http\Requests\ReopenRentalReturnRequest;
use App\Http\Requests\StoreDirectRentalRequest;
use App\Http\Requests\StoreRentalFinancialAdjustmentRequest;
use App\Http\Requests\StoreRentalReturnRequest;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RatePlan;
use App\Models\Rental;
use App\Models\RentalCollateral;
use App\Models\RentalPackage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RentalController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('rentals.view');
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $operationalState = $request->string('operational_state')->toString();
        $paymentState = $request->string('payment_state')->toString();
        $source = $request->string('source')->toString();
        $checkoutPeriod = $request->string('checkout_period')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $branches = $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']);
        $branchIds = $branches->pluck('id');

        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }

        $activeStatuses = ['active', 'partial_return', 'correction_pending'];
        $base = Rental::query()->whereIn('branch_id', $branchIds);
        $scope = (clone $base)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId));
        $query = (clone $scope)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('rental_number', 'like', "%{$search}%")
                        ->orWhere('legacy_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('booking', fn (Builder $booking) => $booking
                            ->where('booking_number', 'like', "%{$search}%"))
                        ->orWhereHas('items.product', fn (Builder $product) => $product
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('items.assets.asset', fn (Builder $asset) => $asset
                            ->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, ['active', 'partial_return', 'correction_pending', 'returned', 'completed'], true),
                fn (Builder $query) => $query->where('status', $status))
            ->when($source === 'direct', fn (Builder $query) => $query
                ->whereHas('booking', fn (Builder $booking) => $booking->where('source', 'direct')))
            ->when($source === 'booking', fn (Builder $query) => $query
                ->whereHas('booking', fn (Builder $booking) => $booking->where('source', '!=', 'direct')))
            ->when($paymentState === 'outstanding', fn (Builder $query) => $query->where('balance_due', '>', 0))
            ->when($paymentState === 'paid', fn (Builder $query) => $query->where('balance_due', '=', 0))
            ->when($paymentState === 'overpaid', fn (Builder $query) => $query->where('balance_due', '<', 0))
            ->when($checkoutPeriod === 'today', fn (Builder $query) => $query->whereDate('checked_out_at', today()))
            ->when($checkoutPeriod === 'last7', fn (Builder $query) => $query->where('checked_out_at', '>=', now()->subDays(7)))
            ->when($checkoutPeriod === 'last30', fn (Builder $query) => $query->where('checked_out_at', '>=', now()->subDays(30)));

        if ($operationalState === 'overdue') {
            $query->whereIn('status', $activeStatuses)->where('due_at', '<', now());
        } elseif ($operationalState === 'due_today') {
            $query->whereIn('status', $activeStatuses)->whereDate('due_at', today());
        } elseif ($operationalState === 'due_soon') {
            $query->whereIn('status', $activeStatuses)
                ->where('due_at', '>=', now())
                ->where('due_at', '<=', now()->addHours(24));
        } elseif ($operationalState === 'active') {
            $query->whereIn('status', $activeStatuses)->where('due_at', '>=', now());
        } elseif ($operationalState === 'closed') {
            $query->whereIn('status', ['returned', 'completed']);
        }

        $rentals = $query
            ->with([
                'branch:id,code,name',
                'customer:id,customer_number,name,phone',
                'booking:id,booking_number,source',
            ])
            ->withCount('items')
            ->orderByRaw("CASE WHEN status IN ('active','partial_return','correction_pending') AND due_at < ? THEN 0 ELSE 1 END", [now()])
            ->orderBy('due_at')
            ->orderByDesc('checked_out_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Rental $rental) use ($activeStatuses): Rental {
                $isOperational = in_array($rental->status, $activeStatuses, true);
                $dueAt = CarbonImmutable::parse((string) $rental->due_at);
                $isOverdue = $isOperational && $dueAt->isPast();
                $isDueToday = $isOperational && $dueAt->isToday();
                $dueState = match (true) {
                    $isOverdue => 'overdue',
                    $isDueToday => 'due_today',
                    $isOperational && $dueAt->between(now(), now()->addHours(24)) => 'due_soon',
                    $isOperational => 'on_track',
                    default => 'closed',
                };

                $rental->setAttribute('is_overdue', $isOverdue);
                $rental->setAttribute('due_state', $dueState);
                $rental->setAttribute(
                    'source_label',
                    $rental->booking?->source === 'direct'
                        ? 'Rental In Store'
                        : ($rental->booking !== null ? 'Checkout Booking' : 'Rental tanpa booking'),
                );

                return $rental;
            });

        return Inertia::render('rentals/index', [
            'rentals' => $rentals,
            'summary' => [
                'active' => (clone $scope)->whereIn('status', $activeStatuses)->count(),
                'overdue' => (clone $scope)->whereIn('status', $activeStatuses)->where('due_at', '<', now())->count(),
                'dueToday' => (clone $scope)->whereIn('status', $activeStatuses)->whereDate('due_at', today())->count(),
                'partialReturn' => (clone $scope)->where('status', 'partial_return')->count(),
                'correctionPending' => (clone $scope)->where('status', 'correction_pending')->count(),
                'closed' => (clone $scope)->whereIn('status', ['returned', 'completed'])->count(),
                'outstandingAmount' => (float) (clone $scope)
                    ->whereIn('status', $activeStatuses)
                    ->where('balance_due', '>', 0)
                    ->sum('balance_due'),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'operational_state' => $operationalState,
                'payment_state' => $paymentState,
                'source' => $source,
                'checkout_period' => $checkoutPeriod,
                'branch_id' => $branchId,
            ],
            'branches' => $branches,
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
            'cashSessions' => $this->cashSessions($request->user()),
        ]);
    }

    /**
     * Fetch Customer360 identities for the currently selected Rental In Store customer.
     * This GET does not receive or hold any collateral; the POST is checkout itself.
     */
    public function directCustomerIdentities(Request $request): JsonResponse
    {
        Gate::authorize('rentals.create');

        $input = $request->validate([
            'customer_id' => ['required', 'integer', 'min:1'],
            'branch_id' => ['required', 'integer', 'min:1'],
        ]);
        abort_unless(
            $request->user()->accessibleBranches()->whereKey((int) $input['branch_id'])->exists(),
            404,
        );

        $customer = Customer::query()
            ->where('company_id', $request->user()->company_id)
            ->where('status', 'active')
            ->with(['identities' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderByDesc('verified_at')
                ->orderByDesc('id')])
            ->findOrFail((int) $input['customer_id']);

        return response()->json([
            'data' => $this->customerIdentityOptions($customer->identities),
        ]);
    }

    public function storeDirect(
        StoreDirectRentalRequest $request,
        RentalManager $manager,
        RentalCollateralDocumentStorage $documents,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $prepared = $documents->prepareCheckoutPayload($request->validated());

        try {
            $rental = $manager->createDirect($prepared['payload'], $request->user());
        } catch (Throwable $exception) {
            $documents->cleanup($prepared['paths']);
            throw $exception;
        }

        $recorder->record(
            $request,
            'rental.direct_checked_out',
            $rental,
            null,
            $this->audit($rental),
            $rental->branch_id,
        );
        $this->recordReceivedCollaterals($request, $rental, $recorder);

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => __('uat035b_stage4.flash.direct_checked_out', ['reference' => $rental->rental_number]),
        ]);
    }

    public function createCheckout(
        Request $request,
        Booking $booking,
        BookingPaymentSettlement $paymentSettlement,
    ): Response
    {
        Gate::authorize('rentals.create');
        $this->guardBookingAccess($request, $booking);
        abort_unless($booking->status === 'confirmed' && ! $booking->rental()->exists(), 409);
        $booking->load([
            'branch:id,code,name',
            'customer:id,customer_number,name,phone',
            'customer.identities' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderByDesc('verified_at')
                ->orderByDesc('id'),
            'ratePlan:id,code,name,duration_unit,duration_value',
            'promotion:id,code,name,type,value,bonus_duration',
            'items.reservations.asset:id,product_id,asset_code,serial_number,status,condition',
            'items.bulkReservations.product:id,sku,name',
        ]);

        $settlement = $paymentSettlement->summary($booking);
        $rentalPaid = $settlement['rental_paid'];
        $depositPaid = $settlement['deposit_paid'];

        $identities = $booking->customer?->identities ?? collect();

        return Inertia::render('rentals/checkout', [
            'booking' => $booking,
            'paymentMethods' => $this->paymentMethods($request->user()),
            'cashSessions' => $this->cashSessions($request->user(), $booking->branch_id),
            'customerIdentities' => $this->customerIdentityOptions($identities),
            'financialSummary' => [
                'rental_paid' => $rentalPaid,
                'deposit_paid' => $depositPaid,
                'balance_due' => max(0, (float) $booking->total_amount - $rentalPaid),
                'deposit_due' => max(0, (float) $booking->deposit_required - $depositPaid),
            ],
        ]);
    }

    public function storeCheckout(
        CheckoutBookingRequest $request,
        Booking $booking,
        RentalManager $manager,
        RentalCollateralDocumentStorage $documents,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardBookingAccess($request, $booking);
        $prepared = $documents->prepareCheckoutPayload($request->validated());

        try {
            $rental = $manager->checkout($booking, $prepared['payload'], $request->user());
        } catch (Throwable $exception) {
            $documents->cleanup($prepared['paths']);
            throw $exception;
        }

        $recorder->record(
            $request,
            'rental.booking_checked_out',
            $rental,
            ['booking_status' => 'confirmed'],
            $this->audit($rental),
            $rental->branch_id,
        );
        $this->recordReceivedCollaterals($request, $rental, $recorder);

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => __('uat035b_stage4.flash.booking_checked_out', ['reference' => $rental->rental_number]),
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
            'customer:id,customer_number,name,phone,email,risk_level,is_member,member_number,member_since',
            'booking:id,booking_number,source',
            'ratePlan:id,code,name,duration_unit,duration_value',
            'promotion:id,code,name,type,value,maximum_discount,minimum_transaction,bonus_duration,rules',
            'items.product:id,sku,name',
            'items.assets.asset:id,product_id,asset_code,serial_number,status,condition',
            'returns:id,rental_id,return_number,type,status,returned_at,total_charge_amount',
            'extensions' => fn ($query) => $query->latest('approved_at')->latest('id'),
            'extensions.items.rentalItem.product:id,sku,name',
            'extensions.creator:id,name',
            'extensions.approver:id,name',
            'extensions.promotion:id,code,name,type,value,bonus_duration',
            'collaterals' => fn ($query) => $query->latest('received_at')->latest('id'),
            'collaterals.receiver:id,name',
            'collaterals.returner:id,name',
            'statusHistories.changer:id,name',
            'payments:id,rental_id,type,amount,status,paid_at,external_reference',
            'financialAdjustments' => fn ($query) => $query->latest(),
            'financialAdjustments.creator:id,name',
            'operationalCorrections' => fn ($query) => $query->latest(),
            'operationalCorrections.originalReturn:id,return_number',
            'operationalCorrections.replacementReturn:id,return_number',
            'operationalCorrections.opener:id,name',
            'operationalCorrections.finalizer:id,name',
        ]);

        $rental->setAttribute(
            'is_overdue',
            in_array($rental->status, ['active', 'partial_return', 'correction_pending'], true)
                && CarbonImmutable::parse((string) $rental->due_at)->isPast(),
        );

        $identities = $rental->customer
            ->identities()
            ->orderByDesc('is_primary')
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->get();
        $eligibleIdentities = $identities->reject(
            fn (CustomerIdentity $identity): bool => $identity->isExpiredAt(now()),
        );
        $defaultIdentity = $eligibleIdentities->first(
            fn (CustomerIdentity $identity): bool => $identity->is_primary && $identity->verified_at !== null,
        ) ?? $eligibleIdentities->first(
            fn (CustomerIdentity $identity): bool => $identity->verified_at !== null,
        ) ?? $eligibleIdentities->first(
            fn (CustomerIdentity $identity): bool => $identity->is_primary,
        ) ?? $eligibleIdentities->first();

        return Inertia::render('rentals/show', [
            'rental' => $rental,
            'customerIdentities' => $identities->map(
                fn (CustomerIdentity $identity): array => [
                    'id' => $identity->id,
                    'type' => $identity->type,
                    'collateral_type' => $identity->collateralType(),
                    'number' => $identity->number,
                    'name_on_identity' => $identity->name_on_identity,
                    'expires_at' => $identity->expires_at?->toDateString(),
                    'is_primary' => $identity->is_primary,
                    'verified_at' => $identity->verified_at?->toISOString(),
                    'document_present' => $identity->document_path !== null,
                    'is_expired' => $identity->isExpiredAt(now()),
                    'is_default' => $defaultIdentity?->id === $identity->id,
                ],
            )->values(),
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function createReturn(
        Request $request,
        Rental $rental,
        RentalOvertimeCalculator $overtime,
    ): Response
    {
        Gate::authorize('rentals.return');
        $this->guardRentalAccess($request, $rental);
        abort_unless(in_array($rental->status, ['active', 'partial_return', 'correction_pending'], true), 409);
        $rental->load([
            'branch:id,code,name',
            'customer:id,customer_number,name,phone',
            'items' => fn ($query) => $query->whereIn('status', ['out', 'partial_return']),
            'items.assets' => fn ($query) => $query->where('status', 'out'),
            'items.assets.asset:id,product_id,asset_code,serial_number,status,condition',
            'collaterals' => fn ($query) => $query
                ->where('status', 'held')
                ->orderBy('received_at')
                ->orderBy('id'),
        ]);

        $previewAt = CarbonImmutable::now();
        $serializedOvertime = [];
        $bulkOvertime = [];

        foreach ($rental->items as $item) {
            if ($item->is_bulk) {
                $remainingQuantity = max(0, (int) $item->quantity - (int) $item->returned_quantity);
                if ($remainingQuantity > 0) {
                    $bulkOvertime[$item->id] = [
                        'per_unit' => $overtime->calculate($item, $previewAt, 1),
                        'remaining' => $overtime->calculate($item, $previewAt, $remainingQuantity),
                    ];
                }

                continue;
            }

            foreach ($item->assets as $unit) {
                $serializedOvertime[$unit->id] = $overtime->calculate($item, $previewAt, 1);
            }
        }

        return Inertia::render('rentals/return', [
            'rental' => $rental,
            'serverNow' => $previewAt->toISOString(),
            'overtimePreview' => [
                'serialized' => $serializedOvertime,
                'bulk' => $bulkOvertime,
            ],
            'paymentMethods' => $this->paymentMethods($request->user()),
            'cashSessions' => $this->cashSessions($request->user(), $rental->branch_id),
            'operationalCorrection' => $rental->status === 'correction_pending'
                ? $rental->operationalCorrections()
                    ->where('status', 'open')
                    ->with('originalReturn:id,return_number,returned_at')
                    ->first()
                : null,
            'replacementAssets' => $rental->status === 'correction_pending'
                ? Asset::query()
                    ->where('current_branch_id', $rental->branch_id)
                    ->whereIn('product_id', $rental->items->pluck('product_id'))
                    ->where('status', 'available')
                    ->where('is_active', true)
                    ->orderBy('asset_code')
                    ->get(['id', 'product_id', 'asset_code', 'serial_number'])
                : [],
        ]);
    }

    public function reopenReturn(
        ReopenRentalReturnRequest $request,
        Rental $rental,
        RentalOperationalCorrectionManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardRentalAccess($request, $rental);
        $before = $this->audit($rental);
        $correction = $manager->reopen($rental, $request->validated(), $request->user());
        $fresh = $rental->fresh();
        $recorder->record(
            $request,
            'rental.return_reopened',
            $fresh,
            $before,
            [
                ...$this->audit($fresh),
                'correction_id' => $correction->id,
                'correction_number' => $correction->correction_number,
                'original_return_id' => $correction->original_return_id,
                'reason' => $correction->reason,
            ],
            $rental->branch_id,
        );

        return to_route('rentals.return.create', $rental)->with('toast', [
            'type' => 'success',
            'message' => __('uat035b_stage4.flash.return_reopened', ['reference' => $correction->correction_number]),
        ]);
    }

    public function storeReturn(
        StoreRentalReturnRequest $request,
        Rental $rental,
        RentalReturnManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardRentalAccess($request, $rental);
        $wasOperationalCorrection = $rental->status === 'correction_pending';
        $validated = $request->validated();
        $rawCollateralIds = $validated['returned_collateral_ids'] ?? [];
        /** @var list<int> $collateralIds */
        $collateralIds = [];
        if (is_array($rawCollateralIds)) {
            foreach ($rawCollateralIds as $id) {
                $collateralIds[] = (int) $id;
            }
            $collateralIds = array_values(array_unique($collateralIds));
        }
        $collateralBefore = RentalCollateral::query()
            ->where('rental_id', $rental->id)
            ->whereIn('id', $collateralIds)
            ->get()
            ->keyBy('id');
        $return = $manager->process($rental, $validated, $request->user());
        $recorder->record(
            $request,
            $wasOperationalCorrection
                ? 'rental.operational_correction_finalized'
                : 'rental.return_completed',
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

        foreach ($collateralIds as $collateralId) {
            $before = $collateralBefore->get($collateralId);
            $after = RentalCollateral::query()->find($collateralId);
            if ($before === null || $after === null || $after->status !== 'returned') {
                continue;
            }
            $recorder->record(
                $request,
                'rental.collateral_returned',
                $after,
                $before->toArray(),
                $after->toArray(),
                $rental->branch_id,
            );
        }

        return to_route('rentals.show', $rental)->with('toast', [
            'type' => 'success',
            'message' => __('uat035b_stage4.flash.return_recorded', ['reference' => $return->return_number]),
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
            'message' => __('uat035b_stage4.flash.financial_correction', ['reference' => $adjustment->adjustment_number]),
        ]);
    }

    /**
     * @param  Collection<int, CustomerIdentity>  $identities
     * @return Collection<int, array<string, mixed>>
     */
    private function customerIdentityOptions(Collection $identities): Collection
    {
        $eligible = $identities->reject(
            fn (CustomerIdentity $identity): bool => $identity->isExpiredAt(now()),
        );
        $default = $eligible->first(
            fn (CustomerIdentity $identity): bool => $identity->is_primary && $identity->verified_at !== null,
        ) ?? $eligible->first(
            fn (CustomerIdentity $identity): bool => $identity->verified_at !== null,
        ) ?? $eligible->first(
            fn (CustomerIdentity $identity): bool => $identity->is_primary,
        ) ?? $eligible->first();

        return $identities->map(fn (CustomerIdentity $identity): array => [
            'id' => $identity->id,
            'type' => $identity->type,
            'collateral_type' => $identity->collateralType(),
            'number' => $identity->number,
            'name_on_identity' => $identity->name_on_identity,
            'expires_at' => $identity->expires_at?->toDateString(),
            'is_primary' => $identity->is_primary,
            'verified_at' => $identity->verified_at?->toISOString(),
            'document_present' => $identity->document_path !== null,
            'is_expired' => $identity->isExpiredAt(now()),
            'is_default' => $default?->id === $identity->id,
        ])->values();
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

    /** @return Collection<int, \stdClass> */
    private function cashSessions(User $user, ?int $branchId = null): Collection
    {
        $branchIds = $user->accessibleBranches()->pluck('id');

        return DB::table('cash_sessions')
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_sessions.cash_register_id')
            ->where('cash_sessions.status', 'open')
            ->whereIn('cash_registers.branch_id', $branchIds)
            ->when($branchId !== null, fn ($query) => $query->where('cash_registers.branch_id', $branchId))
            ->orderBy('cash_registers.name')
            ->get([
                'cash_sessions.id',
                'cash_registers.branch_id',
                'cash_registers.name as register_name',
                'cash_sessions.opened_at',
            ]);
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
            'extend' => $user->can('rentals.extend'),
            'return' => $user->can('rentals.return'),
            'correctCompleted' => $user->can('rentals.correct_completed'),
            'reopenReturn' => $user->can('rentals.reopen_return'),
            'updateCustomer' => $user->can('customers.update'),
        ];
    }

    private function recordReceivedCollaterals(
        Request $request,
        Rental $rental,
        ActivityRecorder $recorder,
    ): void {
        foreach ($rental->collaterals as $collateral) {
            $recorder->record(
                $request,
                'rental.collateral_received',
                $collateral,
                null,
                $collateral->only([
                    'id', 'rental_id', 'customer_id', 'customer_identity_id',
                    'source_type', 'type', 'number', 'holder_name', 'identity_snapshot',
                    'status', 'received_at', 'received_by', 'document_path',
                ]),
                $rental->branch_id,
            );
        }
    }

    /** @return array<string, mixed> */
    private function audit(Rental $rental): array
    {
        return $rental->only([
            'id', 'rental_number', 'branch_id', 'booking_id', 'customer_id', 'promotion_id',
            'status', 'checked_out_at', 'due_at', 'subtotal', 'discount_amount', 'total_amount',
            'paid_amount', 'deposit_amount', 'balance_due',
        ]);
    }
}
