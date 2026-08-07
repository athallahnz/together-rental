<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Bookings\BookingManager;
use App\Http\Requests\BookingAvailabilityRequest;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\SaveBookingRequest;
use App\Http\Requests\StoreBookingPaymentRequest;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\RatePlan;
use App\Models\RentalPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('bookings.view');
        $user = $request->user();
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $source = $request->string('source')->toString();
        $period = $request->string('period')->toString();
        $branchId = $request->integer('branch_id') ?: null;
        $branches = $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']);
        $branchIds = $branches->pluck('id');

        if ($branchId !== null) {
            abort_unless($branchIds->contains($branchId), 403);
        }

        $base = Booking::query()->whereIn('branch_id', $branchIds);
        $scope = (clone $base)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId));

        $bookings = (clone $scope)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('booking_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn (Builder $customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_number', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('items.product', fn (Builder $product) => $product
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"))
                        ->orWhereHas('items.package', fn (Builder $package) => $package
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"))
                        ->orWhereHas('reservations.asset', fn (Builder $asset) => $asset
                            ->where('asset_code', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($status, Booking::STATUSES, true), fn (Builder $query) => $query->where('status', $status))
            ->when(in_array($source, ['counter', 'phone', 'whatsapp', 'website', 'other', 'direct'], true),
                fn (Builder $query) => $query->where('source', $source))
            ->when($period === 'today', fn (Builder $query) => $query->whereDate('starts_at', today()))
            ->when($period === 'tomorrow', fn (Builder $query) => $query->whereDate('starts_at', today()->addDay()))
            ->when($period === 'next7', fn (Builder $query) => $query
                ->where('starts_at', '>=', now()->startOfDay())
                ->where('starts_at', '<', now()->addDays(7)->endOfDay()))
            ->when($period === 'upcoming', fn (Builder $query) => $query->where('starts_at', '>=', now()))
            ->when($period === 'past', fn (Builder $query) => $query->where('ends_at', '<', now()))
            ->with(['branch:id,code,name', 'customer:id,customer_number,name,phone'])
            ->withCount(['items', 'reservations'])
            ->orderByDesc('booked_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('bookings/index', [
            'bookings' => $bookings,
            'summary' => [
                'total' => (clone $scope)->count(),
                'draft' => (clone $scope)->where('status', 'draft')->count(),
                'confirmed' => (clone $scope)->where('status', 'confirmed')->count(),
                'today' => (clone $scope)->whereDate('starts_at', today())->count(),
                'expired' => (clone $scope)->where('status', 'expired')->count(),
                'upcoming' => (clone $scope)
                    ->whereIn('status', ['draft', 'confirmed'])
                    ->whereBetween('starts_at', [now(), now()->addDays(7)])
                    ->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'source' => $source,
                'period' => $period,
                'branch_id' => $branchId,
            ],
            'branches' => $branches,
            'permissions' => $this->permissions($user),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('bookings.create');

        return Inertia::render('bookings/form', $this->formOptions($request));
    }

    public function options(Request $request): JsonResponse
    {
        Gate::authorize('bookings.view');

        $validated = $request->validate([
            'type' => ['required', 'in:customer,product,package'],
            'q' => ['required', 'string', 'min:2', 'max:80'],
            'branch_id' => ['nullable', 'integer'],
            'rate_plan_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $type = $validated['type'];
        $search = trim($validated['q']);

        if ($type === 'customer') {
            $customers = Customer::query()
                ->where('company_id', $user->company_id)
                ->where('status', 'active')
                ->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'customer_number', 'name', 'phone']);

            return response()->json(['data' => $customers]);
        }

        $branchId = (int) ($validated['branch_id'] ?? 0);
        $ratePlanId = (int) ($validated['rate_plan_id'] ?? 0);
        abort_unless(
            $branchId > 0
            && $ratePlanId > 0
            && $user->accessibleBranches()->whereKey($branchId)->exists(),
            422,
        );

        if ($type === 'product') {
            $products = Product::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->where('is_rentable', true)
                ->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%")
                    ->orWhere('variant', 'like', "%{$search}%")
                    ->orWhereHas('catalogBrand', fn (Builder $brand) => $brand
                        ->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('catalogModel', fn (Builder $model) => $model
                        ->where('name', 'like', "%{$search}%")))
                ->whereHas('rates', fn (Builder $query) => $query
                    ->where('rate_plan_id', $ratePlanId)
                    ->where('is_active', true)
                    ->where(fn (Builder $rate) => $rate
                        ->whereNull('branch_id')
                        ->orWhere('branch_id', $branchId)))
                ->with('catalogBrand:id,name,logo_path')
                ->orderByRaw(
                    'case when name like ? then 0 when sku like ? then 1 else 2 end',
                    ["{$search}%", "{$search}%"],
                )
                ->orderBy('name')
                ->limit(15)
                ->get([
                    'id',
                    'sku',
                    'name',
                    'brand',
                    'model',
                    'variant',
                    'catalog_brand_id',
                    'primary_image_path',
                ])
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'brand' => $product->catalog_brand_id !== null
                        ? $product->catalogBrand->name
                        : $product->brand,
                    'model' => $product->model,
                    'variant' => $product->variant,
                    'image_url' => $this->mediaUrl($product->primary_image_path),
                    'brand_logo_url' => $product->catalog_brand_id !== null
                        ? $product->catalogBrand->logo_url
                        : null,
                ]);

            return response()->json(['data' => $products]);
        }

        $packages = RentalPackage::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('branch_id')
                ->orWhere('branch_id', $branchId))
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%"))
            ->whereHas('rates', fn (Builder $query) => $query
                ->where('rate_plan_id', $ratePlanId)
                ->where('is_active', true)
                ->where(fn (Builder $rate) => $rate
                    ->whereNull('branch_id')
                    ->orWhere('branch_id', $branchId)))
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'branch_id', 'code', 'name', 'primary_image_path'])
            ->map(fn (RentalPackage $package): array => [
                'id' => $package->id,
                'branch_id' => $package->branch_id,
                'code' => $package->code,
                'name' => $package->name,
                'image_url' => $this->mediaUrl($package->primary_image_path),
            ]);

        return response()->json(['data' => $packages]);
    }

    private function mediaUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (
            str_starts_with($path, 'http://')
            || str_starts_with($path, 'https://')
            || str_starts_with($path, '/')
        ) {
            return $path;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($path);
    }

    public function store(
        SaveBookingRequest $request,
        BookingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $booking = $manager->create($request->validated(), $request->user());
        $recorder->record($request, 'booking.created', $booking, null, $this->audit($booking), $booking->branch_id);

        return to_route('bookings.show', $booking)->with('toast', [
            'type' => 'success', 'message' => "Booking {$booking->booking_number} berhasil dibuat.",
        ]);
    }

    public function show(Request $request, Booking $booking): Response
    {
        Gate::authorize('bookings.view');
        $this->guardAccess($request, $booking);
        $booking->load([
            'branch:id,code,name', 'customer:id,customer_number,name,phone,email,risk_level',
            'ratePlan:id,code,name,duration_unit,duration_value',
            'handler:id,name', 'creator:id,name', 'canceller:id,name',
            'items.product:id,sku,name', 'items.package:id,code,name',
            'items.reservations.asset:id,asset_code,serial_number,status,condition',
            'statusHistories.changer:id,name',
            'payments:id,booking_id,payment_method_id,payment_number,type,status,amount,paid_at,external_reference',
            'payments.paymentMethod:id,name',
        ]);

        return Inertia::render('bookings/show', [
            'booking' => $booking,
            'permissions' => $this->permissions($request->user()),
            'financialSummary' => [
                'rental_paid' => (float) $booking->payments
                    ->where('status', 'completed')->where('type', 'rental')->sum('amount'),
                'deposit_paid' => (float) $booking->payments
                    ->where('status', 'completed')->where('type', 'deposit')->sum('amount'),
            ],
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $request->user()->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'requires_reference']),
        ]);
    }

    public function edit(Request $request, Booking $booking): Response
    {
        Gate::authorize('bookings.update');
        $this->guardAccess($request, $booking);
        abort_unless($booking->status === 'draft', 409);
        $booking->load('items');

        return Inertia::render('bookings/form', [
            ...$this->formOptions($request, $booking),
            'booking' => $booking,
        ]);
    }

    public function update(
        SaveBookingRequest $request,
        Booking $booking,
        BookingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $booking);
        $old = $this->audit($booking);
        $updated = $manager->update($booking, $request->validated(), $request->user());
        $recorder->record($request, 'booking.updated', $updated, $old, $this->audit($updated), $updated->branch_id);

        return to_route('bookings.show', $updated)->with('toast', [
            'type' => 'success', 'message' => "Booking {$updated->booking_number} berhasil diperbarui.",
        ]);
    }

    public function confirm(
        Request $request,
        Booking $booking,
        BookingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('bookings.update');
        $this->guardAccess($request, $booking);
        $old = $this->audit($booking);
        $confirmed = $manager->confirm($booking, $request->user());
        $recorder->record($request, 'booking.confirmed', $confirmed, $old, $this->audit($confirmed), $confirmed->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Booking berhasil dikonfirmasi.']);
    }

    public function storePayment(
        StoreBookingPaymentRequest $request,
        Booking $booking,
        BookingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $booking);
        $updated = $manager->receivePayment($booking, $request->validated(), $request->user());
        $recorder->record(
            $request,
            'booking.payment_received',
            $updated,
            null,
            $this->audit($updated),
            $updated->branch_id,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pembayaran booking berhasil dicatat.',
        ]);
    }

    public function cancel(
        CancelBookingRequest $request,
        Booking $booking,
        BookingManager $manager,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardAccess($request, $booking);
        $old = $this->audit($booking);
        $cancelled = $manager->cancel($booking, $request->string('reason')->toString(), $request->user());
        $recorder->record($request, 'booking.cancelled', $cancelled, $old, $this->audit($cancelled), $cancelled->branch_id);

        return back()->with('toast', ['type' => 'success', 'message' => 'Booking berhasil dibatalkan dan reservasi aset dilepas.']);
    }

    public function availability(BookingAvailabilityRequest $request, BookingManager $manager): JsonResponse
    {
        return response()->json($manager->availability(
            $request->integer('branch_id'),
            $request->integer('product_id'),
            $request->string('starts_at')->toString(),
            $request->integer('rate_plan_id'),
            $request->integer('duration_units'),
            $request->integer('quantity'),
            $request->integer('ignore_booking_id') ?: null,
        ));
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request, ?Booking $booking = null): array
    {
        $user = $request->user();
        $branchIds = $user->accessibleBranches()->pluck('id');
        $productIds = $booking?->items->pluck('product_id')->filter()->values() ?? collect();
        $packageIds = $booking?->items->pluck('package_id')->filter()->values() ?? collect();

        return [
            'booking' => null,
            'branches' => $user->accessibleBranches()->orderBy('name')->get(['id', 'code', 'name']),
            'customers' => Customer::query()
                ->whereKey($booking instanceof Booking ? $booking->customer_id : 0)
                ->get(['id', 'customer_number', 'name', 'phone']),
            'ratePlans' => RatePlan::query()->where('company_id', $user->company_id)->where('is_active', true)
                ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhereIn('branch_id', $branchIds))
                ->orderBy('name')->get(['id', 'branch_id', 'code', 'name', 'duration_unit', 'duration_value']),
            'products' => Product::query()
                ->whereIn('id', $productIds)
                ->get(['id', 'sku', 'name']),
            'packages' => RentalPackage::query()
                ->whereIn('id', $packageIds)
                ->get(['id', 'branch_id', 'code', 'name']),
            'paymentMethods' => PaymentMethod::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'requires_reference']),
        ];
    }

    private function guardAccess(Request $request, Booking $booking): void
    {
        abort_unless($request->user()->accessibleBranches()->whereKey($booking->branch_id)->exists(), 404);
    }

    /** @return array<string, bool> */
    private function permissions(User $user): array
    {
        return [
            'create' => $user->can('bookings.create'),
            'update' => $user->can('bookings.update'),
            'cancel' => $user->can('bookings.cancel'),
            'checkout' => $user->can('rentals.create'),
            'payment' => $user->can('payments.create'),
        ];
    }

    /** @return array<string, mixed> */
    private function audit(Booking $booking): array
    {
        return $booking->only(['id', 'booking_number', 'branch_id', 'customer_id', 'status', 'starts_at', 'ends_at', 'total_amount']);
    }
}
