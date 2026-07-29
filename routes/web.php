<?php

use App\Http\Controllers\AssetAnalyticsController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchPublicProfileController;
use App\Http\Controllers\CatalogBrandController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CatalogIntelligenceController;
use App\Http\Controllers\CustomerAddressController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerIdentityController;
use App\Http\Controllers\CustomerLoyaltyController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LegacyImportController;
use App\Http\Controllers\PackageItemController;
use App\Http\Controllers\PackageRateController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductRateController;
use App\Http\Controllers\PublicCatalogContentController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicSitemapController;
use App\Http\Controllers\RatePlanController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RentalPackageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicCatalogController::class, 'home'])->name('home');
Route::get('/sitemap.xml', PublicSitemapController::class)->name('public.sitemap');
Route::prefix('rental')->name('public.catalog.')->group(function (): void {
    Route::get('/', [PublicCatalogController::class, 'index'])->name('index');
    Route::get('/availability', [PublicCatalogController::class, 'availability'])
        ->middleware('throttle:60,1')
        ->name('availability');
    Route::get('/products/{slug}', [PublicCatalogController::class, 'product'])
        ->where('slug', '[a-z0-9-]+')
        ->name('products.show');
    Route::get('/packages/{slug}', [PublicCatalogController::class, 'package'])
        ->where('slug', '[a-z0-9-]+')
        ->name('packages.show');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])
            ->middleware('can:products.view')
            ->name('index');
        Route::get('/public-content', [PublicCatalogContentController::class, 'index'])
            ->middleware('can:products.manage')
            ->name('public-content.index');
        Route::put('/public-content/products/{product}', [PublicCatalogContentController::class, 'updateProduct'])
            ->middleware('can:products.manage')
            ->name('public-content.products.update');
        Route::put('/public-content/packages/{rentalPackage}', [PublicCatalogContentController::class, 'updatePackage'])
            ->middleware('can:products.manage')
            ->name('public-content.packages.update');
        Route::put('/public-content/categories/{productCategory}', [PublicCatalogContentController::class, 'updateCategory'])
            ->middleware('can:products.manage')
            ->name('public-content.categories.update');
        Route::put('/public-content/brands/{catalogBrand}', [PublicCatalogContentController::class, 'updateBrand'])
            ->middleware('can:products.manage')
            ->name('public-content.brands.update');
        Route::get('/intelligence', [CatalogIntelligenceController::class, 'index'])
            ->middleware('can:products.manage')
            ->name('intelligence.index');
        Route::post('/intelligence/generate', [CatalogIntelligenceController::class, 'generate'])
            ->middleware('can:products.manage')
            ->name('intelligence.generate');
        Route::patch('/intelligence/candidates/{candidate}', [CatalogIntelligenceController::class, 'review'])
            ->middleware('can:products.manage')
            ->name('intelligence.candidates.review');
        Route::post('/intelligence/runs/{run}/approve-high-confidence', [CatalogIntelligenceController::class, 'approveHighConfidence'])
            ->middleware('can:products.manage')
            ->name('intelligence.approve-high-confidence');
        Route::post('/intelligence/runs/{run}/execute', [CatalogIntelligenceController::class, 'execute'])
            ->middleware('can:products.manage')
            ->name('intelligence.execute');
        Route::post('/intelligence/runs/{run}/rollback', [CatalogIntelligenceController::class, 'rollback'])
            ->middleware('can:products.manage')
            ->name('intelligence.rollback');
        Route::post('/brands/{catalogBrand}/visual', [CatalogBrandController::class, 'updateVisual'])
            ->middleware('can:products.manage')
            ->name('brands.visual.update');
        Route::delete('/brands/{catalogBrand}/logo', [CatalogBrandController::class, 'destroyLogo'])
            ->middleware('can:products.manage')
            ->name('brands.logo.destroy');
        Route::post('/categories', [ProductCategoryController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('categories.store');
        Route::put('/categories/{productCategory}', [ProductCategoryController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('categories.update');
        Route::delete('/categories/{productCategory}', [ProductCategoryController::class, 'destroy'])
            ->middleware('can:products.manage')
            ->name('categories.destroy');
        Route::post('/products', [ProductController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('products.store');
        Route::get('/products/{product}', [CatalogController::class, 'product'])
            ->middleware('can:products.view')
            ->name('products.show');
        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'archive'])
            ->middleware('can:products.manage')
            ->name('products.archive');
        Route::post('/products/{product}/rates', [ProductRateController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('product-rates.store');
        Route::put('/product-rates/{productRate}', [ProductRateController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('product-rates.update');
        Route::delete('/product-rates/{productRate}', [ProductRateController::class, 'destroy'])
            ->middleware('can:products.manage')
            ->name('product-rates.destroy');
        Route::post('/rate-plans', [RatePlanController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('rate-plans.store');
        Route::put('/rate-plans/{ratePlan}', [RatePlanController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('rate-plans.update');
        Route::post('/packages', [RentalPackageController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('packages.store');
        Route::get('/packages/{rentalPackage}', [CatalogController::class, 'package'])
            ->middleware('can:products.view')
            ->name('packages.show');
        Route::put('/packages/{rentalPackage}', [RentalPackageController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('packages.update');
        Route::delete('/packages/{rentalPackage}', [RentalPackageController::class, 'archive'])
            ->middleware('can:products.manage')
            ->name('packages.archive');
        Route::post('/packages/{rentalPackage}/items', [PackageItemController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('package-items.store');
        Route::delete('/package-items/{packageItem}', [PackageItemController::class, 'destroy'])
            ->middleware('can:products.manage')
            ->name('package-items.destroy');
        Route::post('/packages/{rentalPackage}/rates', [PackageRateController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('package-rates.store');
        Route::put('/package-rates/{packageRate}', [PackageRateController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('package-rates.update');
        Route::delete('/package-rates/{packageRate}', [PackageRateController::class, 'destroy'])
            ->middleware('can:products.manage')
            ->name('package-rates.destroy');
    });

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])
            ->middleware('can:customers.view')
            ->name('index');
        Route::post('/', [CustomerController::class, 'store'])
            ->middleware('can:customers.create')
            ->name('store');
        Route::get('/{customer}', [CustomerController::class, 'show'])
            ->middleware('can:customers.view')
            ->name('show');
        Route::put('/{customer}', [CustomerController::class, 'update'])
            ->middleware('can:customers.update')
            ->name('update');
        Route::delete('/{customer}', [CustomerController::class, 'archive'])
            ->middleware('can:customers.delete')
            ->name('archive');
        Route::post('/{customer}/identities', [CustomerIdentityController::class, 'store'])
            ->middleware('can:customers.update')
            ->name('identities.store');
        Route::post('/{customer}/addresses', [CustomerAddressController::class, 'store'])
            ->middleware('can:customers.update')
            ->name('addresses.store');
        Route::post('/{customer}/loyalty-adjustments', [CustomerLoyaltyController::class, 'store'])
            ->middleware('can:customers.loyalty')
            ->name('loyalty.store');
    });

    Route::prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', [BookingController::class, 'index'])
            ->middleware('can:bookings.view')->name('index');
        Route::get('/create', [BookingController::class, 'create'])
            ->middleware('can:bookings.create')->name('create');
        Route::post('/', [BookingController::class, 'store'])
            ->middleware('can:bookings.create')->name('store');
        Route::get('/availability', [BookingController::class, 'availability'])
            ->middleware('can:bookings.view')->name('availability');
        Route::get('/options', [BookingController::class, 'options'])
            ->middleware('can:bookings.view')
            ->middleware('throttle:120,1')
            ->name('options');
        Route::get('/{booking}', [BookingController::class, 'show'])
            ->middleware('can:bookings.view')->name('show');
        Route::get('/{booking}/edit', [BookingController::class, 'edit'])
            ->middleware('can:bookings.update')->name('edit');
        Route::put('/{booking}', [BookingController::class, 'update'])
            ->middleware('can:bookings.update')->name('update');
        Route::post('/{booking}/confirm', [BookingController::class, 'confirm'])
            ->middleware('can:bookings.update')->name('confirm');
        Route::post('/{booking}/payments', [BookingController::class, 'storePayment'])
            ->middleware('can:payments.create')->name('payments.store');
        Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])
            ->middleware('can:bookings.cancel')->name('cancel');
    });

    Route::prefix('rentals')->name('rentals.')->group(function () {
        Route::get('/', [RentalController::class, 'index'])
            ->middleware('can:rentals.view')->name('index');
        Route::get('/direct/create', [RentalController::class, 'createDirect'])
            ->middleware('can:rentals.create')->name('direct.create');
        Route::post('/direct', [RentalController::class, 'storeDirect'])
            ->middleware('can:rentals.create')->name('direct.store');
        Route::get('/checkout/{booking}', [RentalController::class, 'createCheckout'])
            ->middleware('can:rentals.create')->name('checkout.create');
        Route::post('/checkout/{booking}', [RentalController::class, 'storeCheckout'])
            ->middleware('can:rentals.create')->name('checkout.store');
        Route::get('/{rental}/return', [RentalController::class, 'createReturn'])
            ->middleware('can:rentals.return')->name('return.create');
        Route::post('/{rental}/return', [RentalController::class, 'storeReturn'])
            ->middleware('can:rentals.return')->name('return.store');
        Route::post('/{rental}/financial-corrections', [RentalController::class, 'storeFinancialCorrection'])
            ->middleware('can:rentals.correct_completed')->name('financial-corrections.store');
        Route::get('/{rental}', [RentalController::class, 'show'])
            ->middleware('can:rentals.view')->name('show');
    });

    Route::prefix('customer-identities')->name('customer-identities.')->group(function () {
        Route::put('/{customerIdentity}', [CustomerIdentityController::class, 'update'])
            ->middleware('can:customers.update')
            ->name('update');
        Route::patch('/{customerIdentity}/verify', [CustomerIdentityController::class, 'verify'])
            ->middleware('can:customers.verify')
            ->name('verify');
        Route::delete('/{customerIdentity}', [CustomerIdentityController::class, 'destroy'])
            ->middleware('can:customers.update')
            ->name('destroy');
    });

    Route::prefix('customer-addresses')->name('customer-addresses.')->group(function () {
        Route::put('/{customerAddress}', [CustomerAddressController::class, 'update'])
            ->middleware('can:customers.update')
            ->name('update');
        Route::delete('/{customerAddress}', [CustomerAddressController::class, 'destroy'])
            ->middleware('can:customers.update')
            ->name('destroy');
    });

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])
            ->middleware('can:users.view')
            ->name('index');
        Route::post('/', [UserManagementController::class, 'store'])
            ->middleware('can:users.manage')
            ->name('store');
        Route::put('/{user}', [UserManagementController::class, 'update'])
            ->middleware('can:users.manage')
            ->name('update');
        Route::patch('/{user}/status', [UserManagementController::class, 'toggleStatus'])
            ->middleware('can:users.manage')
            ->name('toggle-status');
    });

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])
            ->middleware('can:users.view')
            ->name('index');
        Route::post('/', [EmployeeController::class, 'store'])
            ->middleware('can:users.manage')
            ->name('store');
        Route::put('/{employee}', [EmployeeController::class, 'update'])
            ->middleware('can:users.manage')
            ->name('update');
        Route::patch('/{employee}/status', [EmployeeController::class, 'toggleStatus'])
            ->middleware('can:users.manage')
            ->name('toggle-status');
    });

    Route::prefix('positions')->name('positions.')->group(function () {
        Route::post('/', [PositionController::class, 'store'])
            ->middleware('can:users.manage')
            ->name('store');
        Route::put('/{position}', [PositionController::class, 'update'])
            ->middleware('can:users.manage')
            ->name('update');
        Route::patch('/{position}/status', [PositionController::class, 'toggleStatus'])
            ->middleware('can:users.manage')
            ->name('toggle-status');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])
            ->middleware('can:roles.view')
            ->name('index');
        Route::post('/', [RoleController::class, 'store'])
            ->middleware('can:roles.manage')
            ->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])
            ->middleware('can:roles.manage')
            ->name('update');
    });

    Route::prefix('branches')->name('branches.')->group(function () {
        Route::get('/', [BranchController::class, 'index'])
            ->middleware('can:branches.view')
            ->name('index');
        Route::post('/', [BranchController::class, 'store'])
            ->middleware('can:branches.manage')
            ->name('store');
        Route::put('/{branch}', [BranchController::class, 'update'])
            ->middleware('can:branches.manage')
            ->name('update');
        Route::patch('/{branch}/status', [BranchController::class, 'toggleStatus'])
            ->middleware('can:branches.manage')
            ->name('toggle-status');
        Route::post('/{branch}/switch', [BranchController::class, 'switch'])
            ->middleware('can:branches.switch')
            ->name('switch');
        Route::get('/{branch}/public-profile', [BranchPublicProfileController::class, 'edit'])
            ->middleware('can:branches.manage')
            ->name('public-profile.edit');
        Route::put('/{branch}/public-profile', [BranchPublicProfileController::class, 'update'])
            ->middleware('can:branches.manage')
            ->name('public-profile.update');
    });

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/asset-analytics', [AssetAnalyticsController::class, 'index'])
            ->middleware('can:reports.view')
            ->name('asset-analytics.index');
        Route::get('/asset-analytics/export', [AssetAnalyticsController::class, 'export'])
            ->middleware('can:reports.export')
            ->name('asset-analytics.export');
    });

    Route::prefix('legacy-imports')->name('legacy-imports.')->group(function () {
        Route::get('/', [LegacyImportController::class, 'index'])
            ->middleware('can:imports.view')
            ->name('index');
        Route::post('/', [LegacyImportController::class, 'store'])
            ->middleware('can:imports.upload')
            ->name('store');
        Route::get('/{legacyImport}', [LegacyImportController::class, 'show'])
            ->middleware('can:imports.view')
            ->name('show');
        Route::post('/{legacyImport}/preview', [LegacyImportController::class, 'preview'])
            ->middleware('can:imports.validate')
            ->name('preview');
        Route::post('/{legacyImport}/validate', [LegacyImportController::class, 'validateBatch'])
            ->middleware('can:imports.validate')
            ->name('validate');
        Route::post('/{legacyImport}/map-branch', [LegacyImportController::class, 'mapBranch'])
            ->middleware('can:imports.validate')
            ->name('map-branch');
        Route::post('/{legacyImport}/execute', [LegacyImportController::class, 'execute'])
            ->middleware('can:imports.execute')
            ->name('execute');
        Route::post('/{legacyImport}/verify', [LegacyImportController::class, 'verify'])
            ->middleware('can:imports.execute')
            ->name('verify');
    });
});

require __DIR__.'/settings.php';
