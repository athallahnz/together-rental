<?php

namespace App\Http\Middleware;

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
                    'imports.view' => $user->can('imports.view'),
                    'imports.upload' => $user->can('imports.upload'),
                    'imports.validate' => $user->can('imports.validate'),
                    'imports.execute' => $user->can('imports.execute'),
                    'reports.view' => $user->can('reports.view'),
                    'reports.export' => $user->can('reports.export'),
                ],
                'currentBranch' => $user?->currentBranch()
                    ->first(['id', 'code', 'name', 'city', 'is_active']),
                'branches' => $user === null
                    ? []
                    : $user->accessibleBranches()
                        ->orderBy('name')
                        ->get(['id', 'code', 'name', 'city']),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
