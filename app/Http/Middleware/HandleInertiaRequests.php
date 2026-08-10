<?php

namespace App\Http\Middleware;

use App\Domain\Access\UserAccessManager;
use App\Domain\Notifications\NotificationInboxService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'canResetOperations' => $user !== null
                    && app()->environment(['local', 'testing', 'staging'])
                    && app(UserAccessManager::class)->isSuperAdministrator($user),
                'permissions' => $user === null ? [] : [
                    'branches.view' => $user->can('branches.view'),
                    'branches.manage' => $user->can('branches.manage'),
                    'branches.switch' => $user->can('branches.switch'),
                    'users.view' => $user->can('users.view'),
                    'users.manage' => $user->can('users.manage'),
                    'roles.view' => $user->can('roles.view'),
                    'roles.manage' => $user->can('roles.manage'),
                    'customers.view' => $user->can('customers.view'),
                    'customers.create' => $user->can('customers.create'),
                    'customers.update' => $user->can('customers.update'),
                    'customers.delete' => $user->can('customers.delete'),
                    'customers.verify' => $user->can('customers.verify'),
                    'customers.loyalty' => $user->can('customers.loyalty'),
                    'products.view' => $user->can('products.view'),
                    'products.manage' => $user->can('products.manage'),
                    'bookings.view' => $user->can('bookings.view'),
                    'bookings.create' => $user->can('bookings.create'),
                    'bookings.update' => $user->can('bookings.update'),
                    'bookings.cancel' => $user->can('bookings.cancel'),
                    'rentals.view' => $user->can('rentals.view'),
                    'rentals.create' => $user->can('rentals.create'),
                    'rentals.update' => $user->can('rentals.update'),
                    'rentals.return' => $user->can('rentals.return'),
                    'finance.dashboard.view' => $user->can('finance.dashboard.view'),
                    'finance.masters.view' => $user->can('finance.masters.view'),
                    'payments.view' => $user->can('payments.view'),
                    'payments.create' => $user->can('payments.create'),
                    'payments.void' => $user->can('payments.void'),
                    'refunds.manage' => $user->can('refunds.manage'),
                    'refunds.view' => $user->can('refunds.view'),
                    'refunds.request' => $user->can('refunds.request'),
                    'refunds.approve' => $user->can('refunds.approve'),
                    'refunds.process' => $user->can('refunds.process'),
                    'refunds.cancel' => $user->can('refunds.cancel'),
                    'cash.view' => $user->can('cash.view'),
                    'cash.manage' => $user->can('cash.manage'),
                    'transfers.view' => $user->can('transfers.view'),
                    'transfers.create' => $user->can('transfers.create'),
                    'transfers.update' => $user->can('transfers.update'),
                    'transfers.approve' => $user->can('transfers.approve'),
                    'transfers.cancel' => $user->can('transfers.cancel'),
                    'transfers.dispatch' => $user->can('transfers.dispatch'),
                    'transfers.receive' => $user->can('transfers.receive'),
                    'transfers.expense' => $user->can('transfers.expense'),
                    'transfers.resolve_discrepancy' => $user->can('transfers.resolve_discrepancy'),
                    'transfers.settings' => $user->can('transfers.settings'),
                    'transfers.override' => $user->can('transfers.override'),
                    'inventory-audits.view' => $user->can('inventory-audits.view'),
                    'inventory-audits.create' => $user->can('inventory-audits.create'),
                    'inventory-audits.count' => $user->can('inventory-audits.count'),
                    'inventory-audits.approve' => $user->can('inventory-audits.approve'),
                    'inventory-audits.resolve' => $user->can('inventory-audits.resolve'),
                    'inventory-audits.cancel' => $user->can('inventory-audits.cancel'),
                    'imports.view' => $user->can('imports.view'),
                    'imports.upload' => $user->can('imports.upload'),
                    'imports.validate' => $user->can('imports.validate'),
                    'imports.execute' => $user->can('imports.execute'),
                    'reports.view' => $user->can('reports.view'),
                    'reports.export' => $user->can('reports.export'),
                    'notifications.view' => $user->can('notifications.view'),
                    'notifications.manage' => $user->can('notifications.manage'),
                ],
                'currentBranch' => $user?->currentBranch()
                    ->first(['id', 'code', 'name', 'city', 'is_active']),
                'branches' => $user === null
                    ? []
                    : $user->accessibleBranches()
                        ->orderBy('name')
                        ->get(['id', 'code', 'name', 'city']),
            ],
            'notificationCenter' => $user === null
                ? ['unread_count' => 0, 'critical_count' => 0, 'recent' => []]
                : app(NotificationInboxService::class)->header($user),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
