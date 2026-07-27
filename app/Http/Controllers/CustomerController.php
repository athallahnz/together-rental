<?php

namespace App\Http\Controllers;

use App\Domain\Access\ActivityRecorder;
use App\Domain\Customers\CustomerNumberGenerator;
use App\Http\Requests\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('customers.view');
        $actor = $request->user();
        $base = Customer::query()->where('company_id', $actor->company_id);
        $query = clone $base;
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();
        $membership = $request->string('membership')->toString();
        $risk = $request->string('risk')->toString();
        $branchId = $request->integer('branch_id') ?: null;

        $customers = $query
            ->when($search !== '', function (Builder $customerQuery) use ($search): void {
                $customerQuery->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('customer_number', 'like', "%{$search}%")
                        ->orWhere('member_number', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('identities', fn (Builder $identityQuery) => $identityQuery
                            ->where('number', 'like', "%{$search}%"));
                });
            })
            ->when(
                in_array($status, ['active', 'inactive', 'blocked'], true),
                fn (Builder $customerQuery) => $customerQuery->where('status', $status),
            )
            ->when(
                in_array($risk, ['low', 'normal', 'high', 'critical'], true),
                fn (Builder $customerQuery) => $customerQuery->where('risk_level', $risk),
            )
            ->when(
                $membership === 'member',
                fn (Builder $customerQuery) => $customerQuery->where('is_member', true),
            )
            ->when(
                $membership === 'regular',
                fn (Builder $customerQuery) => $customerQuery->where('is_member', false),
            )
            ->when(
                $branchId !== null,
                fn (Builder $customerQuery) => $customerQuery->where('registered_branch_id', $branchId),
            )
            ->with([
                'registeredBranch:id,code,name',
                'primaryIdentity:id,customer_id,type,number,verified_at',
                'loyaltyAccount:id,customer_id,points_balance,lifetime_points,tier,is_active',
            ])
            ->withCount(['identities', 'rentals', 'bookings'])
            ->withMax('rentals as last_rental_at', 'checked_out_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('customers/index', [
            'customers' => $customers,
            'summary' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('status', 'active')->count(),
                'members' => (clone $base)->where('is_member', true)->count(),
                'highRisk' => (clone $base)
                    ->whereIn('risk_level', ['high', 'critical'])
                    ->count(),
                'unverified' => (clone $base)
                    ->where(function (Builder $customerQuery): void {
                        $customerQuery
                            ->whereDoesntHave('identities')
                            ->orWhereHas(
                                'identities',
                                fn (Builder $identityQuery) => $identityQuery
                                    ->whereNull('verified_at'),
                            );
                    })
                    ->count(),
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'membership' => $membership,
                'risk' => $risk,
                'branch_id' => $branchId,
            ],
            'branches' => $actor->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'permissions' => $this->permissions($actor),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        Gate::authorize('customers.view');
        $this->guardCompany($request, $customer);

        $customer->load([
            'registeredBranch:id,code,name',
            'identities' => fn ($query) => $query
                ->with('verifier:id,name')
                ->orderByDesc('is_primary')
                ->orderByDesc('id'),
            'addresses' => fn ($query) => $query
                ->orderByDesc('is_primary')
                ->orderBy('type'),
            'loyaltyAccount.transactions' => fn ($query) => $query
                ->with(['branch:id,code,name', 'creator:id,name'])
                ->latest('occurred_at')
                ->limit(20),
        ]);

        $recentRentals = $customer->rentals()
            ->with('branch:id,code,name')
            ->latest('checked_out_at')
            ->limit(10)
            ->get([
                'id',
                'branch_id',
                'rental_number',
                'legacy_number',
                'status',
                'checked_out_at',
                'due_at',
                'returned_at',
                'total_amount',
                'paid_amount',
                'balance_due',
            ]);
        $recentBookings = $customer->bookings()
            ->with('branch:id,code,name')
            ->latest('booked_at')
            ->limit(10)
            ->get([
                'id',
                'branch_id',
                'booking_number',
                'legacy_number',
                'status',
                'booked_at',
                'starts_at',
                'ends_at',
                'total_amount',
            ]);
        $rentalBase = $customer->rentals();

        return Inertia::render('customers/show', [
            'customer' => $customer,
            'statistics' => [
                'rentals' => (clone $rentalBase)->count(),
                'activeRentals' => (clone $rentalBase)
                    ->whereNotIn('status', ['returned', 'cancelled', 'void'])
                    ->count(),
                'rentalValue' => (string) (clone $rentalBase)->sum('total_amount'),
                'paidAmount' => (string) DB::table('payments')
                    ->where('customer_id', $customer->id)
                    ->where('status', 'completed')
                    ->where('direction', 'in')
                    ->sum('amount'),
                'outstanding' => (string) (clone $rentalBase)->sum('balance_due'),
                'lastRentalAt' => (clone $rentalBase)->max('checked_out_at'),
            ],
            'recentRentals' => $recentRentals,
            'recentBookings' => $recentBookings,
            'branches' => $request->user()->accessibleBranches()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function store(
        SaveCustomerRequest $request,
        CustomerNumberGenerator $numberGenerator,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $validated = $request->validated();
        $actor = $request->user();

        $customer = DB::transaction(function () use (
            $actor,
            $numberGenerator,
            $recorder,
            $request,
            $validated,
        ): Customer {
            $branch = $actor->accessibleBranches()
                ->whereKey($validated['registered_branch_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $customerNumber = $numberGenerator->next($branch);
            $customer = Customer::query()->create([
                ...$validated,
                'company_id' => $actor->company_id,
                'customer_number' => $customerNumber,
                'member_number' => $validated['is_member']
                    ? ($validated['member_number'] ?: "MBR-{$customerNumber}")
                    : null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $recorder->record(
                $request,
                'customer.created',
                $customer,
                null,
                $this->auditValues($customer),
                $branch->id,
            );

            return $customer;
        });

        return to_route('customers.show', $customer)->with('toast', [
            'type' => 'success',
            'message' => "Pelanggan {$customer->name} berhasil dibuat.",
        ]);
    }

    public function update(
        SaveCustomerRequest $request,
        Customer $customer,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        $this->guardCompany($request, $customer);
        $validated = $request->validated();
        $oldValues = $this->auditValues($customer);

        DB::transaction(function () use (
            $customer,
            $oldValues,
            $recorder,
            $request,
            $validated,
        ): void {
            $customer->update([
                ...$validated,
                'member_number' => $validated['is_member']
                    ? ($validated['member_number'] ?: "MBR-{$customer->customer_number}")
                    : null,
                'updated_by' => $request->user()->id,
            ]);
            $recorder->record(
                $request,
                'customer.updated',
                $customer,
                $oldValues,
                $this->auditValues($customer->fresh()),
                $customer->registered_branch_id,
            );
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Pelanggan {$customer->name} berhasil diperbarui.",
        ]);
    }

    public function archive(
        Request $request,
        Customer $customer,
        ActivityRecorder $recorder,
    ): RedirectResponse {
        Gate::authorize('customers.delete');
        $this->guardCompany($request, $customer);

        if (
            $customer->rentals()
                ->whereNotIn('status', ['returned', 'cancelled', 'void'])
                ->exists()
            || $customer->bookings()
                ->whereNotIn('status', ['completed', 'cancelled', 'converted'])
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'customer' => 'Pelanggan masih memiliki booking atau rental aktif.',
            ]);
        }

        $customer->delete();
        $recorder->record(
            $request,
            'customer.archived',
            $customer,
            $this->auditValues($customer),
            null,
            $customer->registered_branch_id,
        );

        return to_route('customers.index')->with('toast', [
            'type' => 'success',
            'message' => "Pelanggan {$customer->name} berhasil diarsipkan.",
        ]);
    }

    private function guardCompany(Request $request, Customer $customer): void
    {
        abort_unless(
            $request->user()->company_id !== null
                && $customer->company_id === $request->user()->company_id,
            404,
        );
    }

    /** @return array<string, bool> */
    private function permissions(User $user): array
    {
        return [
            'create' => $user->can('customers.create'),
            'update' => $user->can('customers.update'),
            'archive' => $user->can('customers.delete'),
            'verify' => $user->can('customers.verify'),
            'loyalty' => $user->can('customers.loyalty'),
        ];
    }

    /** @return array<string, mixed> */
    private function auditValues(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customer_number' => $customer->customer_number,
            'name' => $customer->name,
            'registered_branch_id' => $customer->registered_branch_id,
            'is_member' => $customer->is_member,
            'status' => $customer->status,
            'risk_level' => $customer->risk_level,
        ];
    }
}
