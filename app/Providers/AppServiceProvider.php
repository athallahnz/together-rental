<?php

namespace App\Providers;

use App\Domain\Catalog\Intelligence\CatalogAiSuggestionProvider;
use App\Domain\Catalog\Intelligence\DisabledCatalogAiSuggestionProvider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            CatalogAiSuggestionProvider::class,
            DisabledCatalogAiSuggestionProvider::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDevelopmentCommands();
        $this->configureAuthorization();
        $this->configureDefaults();
    }

    /**
     * Configure application permissions backed by the V2 role tables.
     */
    protected function configureAuthorization(): void
    {
        foreach ([
            'branches.view',
            'branches.manage',
            'branches.switch',
            'users.view',
            'users.manage',
            'roles.view',
            'roles.manage',
            'customers.view',
            'customers.create',
            'customers.update',
            'customers.delete',
            'customers.verify',
            'customers.loyalty',
            'products.view',
            'products.manage',
            'bookings.view',
            'bookings.create',
            'bookings.update',
            'bookings.cancel',
            'rentals.view',
            'rentals.create',
            'rentals.update',
            'rentals.extend',
            'rentals.return',
            'rentals.correct_completed',
            'rentals.reopen_return',
            'transfers.view',
            'transfers.create',
            'transfers.update',
            'transfers.approve',
            'transfers.cancel',
            'transfers.dispatch',
            'transfers.receive',
            'transfers.expense',
            'transfers.resolve_discrepancy',
            'transfers.settings',
            'transfers.override',
            'maintenance.view',
            'maintenance.manage',
            'finance.dashboard.view',
            'finance.masters.view',
            'finance.payment_methods.manage',
            'finance.categories.manage',
            'finance.cash_registers.manage',
            'payments.view',
            'payments.create',
            'payments.void',
            'refunds.manage',
            'refunds.view',
            'refunds.request',
            'refunds.approve',
            'refunds.process',
            'refunds.cancel',
            'cash.view',
            'cash.manage',
            'imports.view',
            'imports.upload',
            'imports.validate',
            'imports.execute',
            'reports.view',
            'reports.export',
        ] as $permission) {
            Gate::define(
                $permission,
                static fn (User $user): bool => $user->hasPermission($permission),
            );
        }
    }

    /**
     * Ensure Laravel's child development processes use Herd's active PHP on Windows.
     */
    protected function configureDevelopmentCommands(): void
    {
        if (! $this->app->runningInConsole() || PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        DevCommands::register(
            'herd php artisan serve --host=localhost',
            'server',
        );

        DevCommands::register(
            'herd php artisan queue:listen --tries=1 --timeout=0',
            'queue',
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
