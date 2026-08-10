<?php

use App\Http\Controllers\AssetAnalyticsController;
use App\Http\Controllers\AssetCalendarController;
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
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Finance\CashRegisterController;
use App\Http\Controllers\Finance\CashSessionController;
use App\Http\Controllers\Finance\FinanceDashboardController;
use App\Http\Controllers\Finance\FinanceMasterController;
use App\Http\Controllers\Finance\FinancialCategoryController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Finance\PaymentMethodController;
use App\Http\Controllers\Finance\RefundController;
use App\Http\Controllers\IntegratedReportController;
use App\Http\Controllers\InventoryAuditController;
use App\Http\Controllers\LegacyImportController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\OperationalDataResetController;
use App\Http\Controllers\PackageItemController;
use App\Http\Controllers\PackageRateController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductRateController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicCatalogContentController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\PublicSitemapController;
use App\Http\Controllers\RatePlanController;
use App\Http\Controllers\RentalCollateralController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\RentalExtensionController;
use App\Http\Controllers\RentalPackageController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Transfers\BranchTransferApprovalController;
use App\Http\Controllers\Transfers\BranchTransferController;
use App\Http\Controllers\Transfers\BranchTransferDispatchController;
use App\Http\Controllers\Transfers\BranchTransferDocumentController;
use App\Http\Controllers\Transfers\BranchTransferExpenseController;
use App\Http\Controllers\Transfers\BranchTransferReceivingController;
use App\Http\Controllers\Transfers\BranchTransferSettingsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicCatalogController::class, 'home'])->name('home');
Route::get('/sitemap.xml', PublicSitemapController::class)->name('public.sitemap');
Route::prefix('rental')->name('public.catalog.')->group(function (): void {
    Route::get('/', [PublicCatalogController::class, 'index'])->name('index');
    Route::get('/availability', [PublicCatalogController::class, 'availability'])
        ->middleware('throttle:60,1')
        ->name('availability');
    Route::get('/products/{slug}/asset-calendar', [AssetCalendarController::class, 'publicProduct'])
        ->where('slug', '[a-z0-9-]+')
        ->middleware('throttle:60,1')
        ->name('products.asset-calendar');
    Route::get('/products/{slug}', [PublicCatalogController::class, 'product'])
        ->where('slug', '[a-z0-9-]+')
        ->name('products.show');
    Route::get('/packages/{slug}', [PublicCatalogController::class, 'package'])
        ->where('slug', '[a-z0-9-]+')
        ->name('packages.show');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('finance')->name('finance.')->group(function (): void {
        Route::get('/dashboard', FinanceDashboardController::class)
            ->middleware('can:finance.dashboard.view')->name('dashboard');
        Route::get('/master-data', FinanceMasterController::class)
            ->middleware('can:finance.masters.view')->name('masters.index');
        Route::post('/payment-methods', [PaymentMethodController::class, 'store'])
            ->middleware('can:finance.payment_methods.manage')->name('payment-methods.store');
        Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
            ->middleware('can:finance.payment_methods.manage')->name('payment-methods.update');
        Route::patch('/payment-methods/{paymentMethod}/status', [PaymentMethodController::class, 'toggleStatus'])
            ->middleware('can:finance.payment_methods.manage')->name('payment-methods.toggle-status');
        Route::post('/financial-categories', [FinancialCategoryController::class, 'store'])
            ->middleware('can:finance.categories.manage')->name('financial-categories.store');
        Route::put('/financial-categories/{financialCategory}', [FinancialCategoryController::class, 'update'])
            ->middleware('can:finance.categories.manage')->name('financial-categories.update');
        Route::patch('/financial-categories/{financialCategory}/status', [FinancialCategoryController::class, 'toggleStatus'])
            ->middleware('can:finance.categories.manage')->name('financial-categories.toggle-status');
        Route::post('/cash-registers', [CashRegisterController::class, 'store'])
            ->middleware('can:finance.cash_registers.manage')->name('cash-registers.store');
        Route::put('/cash-registers/{cashRegister}', [CashRegisterController::class, 'update'])
            ->middleware('can:finance.cash_registers.manage')->name('cash-registers.update');
        Route::patch('/cash-registers/{cashRegister}/status', [CashRegisterController::class, 'toggleStatus'])
            ->middleware('can:finance.cash_registers.manage')->name('cash-registers.toggle-status');
        Route::get('/payments', [PaymentController::class, 'index'])
            ->middleware('can:payments.view')->name('payments.index');
        Route::get('/payments/{payment}', [PaymentController::class, 'show'])
            ->middleware('can:payments.view')->name('payments.show');
        Route::post('/payments/{payment}/refunds', [RefundController::class, 'store'])
            ->middleware('can:refunds.request')->name('payments.refunds.store');
        Route::get('/refunds', [RefundController::class, 'index'])
            ->middleware('can:refunds.view')->name('refunds.index');
        Route::get('/refunds/{refund}', [RefundController::class, 'show'])
            ->middleware('can:refunds.view')->name('refunds.show');
        Route::get('/refunds/{refund}/proof', [RefundController::class, 'proof'])
            ->middleware('can:refunds.view')->name('refunds.proof');
        Route::post('/refunds/{refund}/approve', [RefundController::class, 'approve'])
            ->middleware('can:refunds.approve')->name('refunds.approve');
        Route::post('/refunds/{refund}/reject', [RefundController::class, 'reject'])
            ->middleware('can:refunds.approve')->name('refunds.reject');
        Route::post('/refunds/{refund}/process', [RefundController::class, 'process'])
            ->middleware('can:refunds.process')->name('refunds.process');
        Route::post('/refunds/{refund}/cancel', [RefundController::class, 'cancel'])
            ->middleware('can:refunds.cancel')->name('refunds.cancel');
        Route::post('/cash-registers/{cashRegister}/sessions', [CashSessionController::class, 'store'])
            ->middleware('can:cash.manage')->name('cash-sessions.store');
        Route::post('/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close'])
            ->middleware('can:cash.manage')->name('cash-sessions.close');
        Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])
            ->middleware('can:payments.void')->name('payments.void');
    });

    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/', [CatalogController::class, 'index'])
            ->middleware('can:products.view')
            ->name('index');
        Route::get('/promotions', [PromotionController::class, 'index'])
            ->middleware('can:products.view')
            ->name('promotions.index');
        Route::post('/promotions', [PromotionController::class, 'store'])
            ->middleware('can:products.manage')
            ->name('promotions.store');
        Route::put('/promotions/{promotion}', [PromotionController::class, 'update'])
            ->middleware('can:products.manage')
            ->name('promotions.update');
        Route::delete('/promotions/{promotion}', [PromotionController::class, 'archive'])
            ->middleware('can:products.manage')
            ->name('promotions.archive');
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
        Route::get('/assets/{asset}/calendar', [AssetCalendarController::class, 'show'])
            ->middleware('can:products.view')
            ->name('assets.calendar');
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
        Route::get('/{rental}/extend', [RentalExtensionController::class, 'create'])
            ->middleware('can:rentals.extend')->name('extend.create');
        Route::post('/{rental}/extensions', [RentalExtensionController::class, 'store'])
            ->middleware('can:rentals.extend')->name('extensions.store');
        Route::post('/{rental}/collaterals', [RentalCollateralController::class, 'store'])
            ->middleware('can:rentals.update')->name('collaterals.store');
        Route::post('/{rental}/collaterals/{collateral}/return', [RentalCollateralController::class, 'markReturned'])
            ->middleware('can:rentals.return')->name('collaterals.return');
        Route::get('/{rental}/collaterals/{collateral}/document', [RentalCollateralController::class, 'document'])
            ->middleware('can:rentals.view')->name('collaterals.document');
        Route::get('/{rental}/return', [RentalController::class, 'createReturn'])
            ->middleware('can:rentals.return')->name('return.create');
        Route::post('/{rental}/return', [RentalController::class, 'storeReturn'])
            ->middleware('can:rentals.return')->name('return.store');
        Route::post('/{rental}/financial-corrections', [RentalController::class, 'storeFinancialCorrection'])
            ->middleware('can:rentals.correct_completed')->name('financial-corrections.store');
        Route::post('/{rental}/operational-corrections', [RentalController::class, 'reopenReturn'])
            ->middleware('can:rentals.reopen_return')->name('operational-corrections.store');
        Route::get('/{rental}', [RentalController::class, 'show'])
            ->middleware('can:rentals.view')->name('show');
    });

    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::get('/settings', [BranchTransferSettingsController::class, 'edit'])
            ->middleware('can:transfers.settings')->name('settings.edit');
        Route::put('/settings', [BranchTransferSettingsController::class, 'update'])
            ->middleware('can:transfers.settings')->name('settings.update');
        Route::get('/', [BranchTransferController::class, 'index'])
            ->middleware('can:transfers.view')->name('index');
        Route::get('/create', [BranchTransferController::class, 'create'])
            ->middleware('can:transfers.create')->name('create');
        Route::post('/', [BranchTransferController::class, 'store'])
            ->middleware('can:transfers.create')->name('store');
        Route::get('/options', [BranchTransferController::class, 'options'])
            ->middleware('can:transfers.view')->middleware('throttle:120,1')->name('options');
        Route::post('/preflight', [BranchTransferController::class, 'preflight'])
            ->name('preflight');
        Route::get('/documents/{document}', [BranchTransferDocumentController::class, 'show'])
            ->middleware('can:transfers.view')->name('documents.show');
        Route::get('/inspection-media/{media}', [BranchTransferDocumentController::class, 'inspectionMedia'])
            ->middleware('can:transfers.view')->name('inspection-media.show');
        Route::get('/{transfer}', [BranchTransferController::class, 'show'])
            ->middleware('can:transfers.view')->name('show');
        Route::get('/{transfer}/edit', [BranchTransferController::class, 'edit'])
            ->middleware('can:transfers.update')->name('edit');
        Route::put('/{transfer}', [BranchTransferController::class, 'update'])
            ->middleware('can:transfers.update')->name('update');
        Route::post('/{transfer}/submit', [BranchTransferController::class, 'submit'])
            ->middleware('can:transfers.create')->name('submit');
        Route::post('/{transfer}/cancel', [BranchTransferController::class, 'cancel'])
            ->middleware('can:transfers.cancel')->name('cancel');
        Route::post('/{transfer}/approvals', [BranchTransferApprovalController::class, 'store'])
            ->middleware('can:transfers.approve')->name('approvals.store');
        Route::post('/{transfer}/dispatch', [BranchTransferDispatchController::class, 'store'])
            ->middleware('can:transfers.dispatch')->name('dispatch.store');
        Route::post('/{transfer}/receipts', [BranchTransferReceivingController::class, 'store'])
            ->middleware('can:transfers.receive')->name('receipts.store');
        Route::post('/{transfer}/items/{item}/resolve', [BranchTransferReceivingController::class, 'resolve'])
            ->middleware('can:transfers.resolve_discrepancy')->name('items.resolve');
        Route::post('/{transfer}/expenses', [BranchTransferExpenseController::class, 'store'])
            ->middleware('can:transfers.expense')->name('expenses.store');
        Route::put('/{transfer}/expenses/{expense}', [BranchTransferExpenseController::class, 'update'])
            ->middleware('can:transfers.expense')->name('expenses.update');
        Route::post('/{transfer}/expenses/{expense}/pay', [BranchTransferExpenseController::class, 'pay'])
            ->middleware('can:transfers.expense')->name('expenses.pay');
        Route::post('/{transfer}/expenses/{expense}/void', [BranchTransferExpenseController::class, 'void'])
            ->middleware('can:transfers.expense')->name('expenses.void');
    });

    Route::prefix('operations')->name('operations.')->group(function () {
        Route::get('/reset', [OperationalDataResetController::class, 'index'])
            ->name('reset.index');
        Route::post('/reset', [OperationalDataResetController::class, 'store'])
            ->name('reset.store');
    });

    Route::prefix('maintenance')->name('maintenance.')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])
            ->middleware('can:maintenance.view')->name('index');
        Route::post('/', [MaintenanceController::class, 'store'])
            ->middleware('can:maintenance.manage')->name('store');
        Route::get('/{maintenance}', [MaintenanceController::class, 'show'])
            ->middleware('can:maintenance.view')->name('show');
        Route::post('/{maintenance}/start', [MaintenanceController::class, 'start'])
            ->middleware('can:maintenance.manage')->name('start');
        Route::post('/{maintenance}/complete', [MaintenanceController::class, 'complete'])
            ->middleware('can:maintenance.manage')->name('complete');
        Route::post('/{maintenance}/cancel', [MaintenanceController::class, 'cancel'])
            ->middleware('can:maintenance.manage')->name('cancel');
    });

    Route::prefix('inventory-audits')->name('inventory-audits.')->group(function () {
        Route::get('/', [InventoryAuditController::class, 'index'])
            ->middleware('can:inventory-audits.view')->name('index');
        Route::post('/', [InventoryAuditController::class, 'store'])
            ->middleware('can:inventory-audits.create')->name('store');
        Route::get('/media/{media}', [InventoryAuditController::class, 'media'])
            ->middleware('can:inventory-audits.view')->name('media');
        Route::get('/{inventoryAudit}', [InventoryAuditController::class, 'show'])
            ->middleware('can:inventory-audits.view')->name('show');
        Route::post('/{inventoryAudit}/start', [InventoryAuditController::class, 'start'])
            ->middleware('can:inventory-audits.count')->name('start');
        Route::post('/{inventoryAudit}/scan', [InventoryAuditController::class, 'scan'])
            ->middleware('can:inventory-audits.count')->name('scan');
        Route::post('/{inventoryAudit}/items/{item}/count', [InventoryAuditController::class, 'recordCount'])
            ->middleware('can:inventory-audits.count')->name('items.count');
        Route::post('/{inventoryAudit}/submit', [InventoryAuditController::class, 'submit'])
            ->middleware('can:inventory-audits.count')->name('submit');
        Route::post('/{inventoryAudit}/approve', [InventoryAuditController::class, 'approve'])
            ->middleware('can:inventory-audits.approve')->name('approve');
        Route::post('/{inventoryAudit}/items/{item}/resolve', [InventoryAuditController::class, 'resolve'])
            ->middleware('can:inventory-audits.resolve')->name('items.resolve');
        Route::post('/{inventoryAudit}/close', [InventoryAuditController::class, 'close'])
            ->middleware('can:inventory-audits.resolve')->name('close');
        Route::post('/{inventoryAudit}/cancel', [InventoryAuditController::class, 'cancel'])
            ->middleware('can:inventory-audits.cancel')->name('cancel');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationCenterController::class, 'index'])
            ->middleware('can:notifications.view')->name('index');
        Route::post('/generate', [NotificationCenterController::class, 'generate'])
            ->middleware(['can:notifications.manage', 'throttle:6,1'])->name('generate');
        Route::post('/mark-all-read', [NotificationCenterController::class, 'markAllRead'])
            ->middleware('can:notifications.view')->name('mark-all-read');
        Route::put('/preferences', [NotificationCenterController::class, 'updatePreferences'])
            ->middleware('can:notifications.view')->name('preferences.update');
        Route::put('/rules/{notificationRule}', [NotificationCenterController::class, 'updateRule'])
            ->middleware('can:notifications.manage')->name('rules.update');
        Route::patch('/{notification}/read', [NotificationCenterController::class, 'markRead'])
            ->middleware('can:notifications.view')->name('read');
        Route::patch('/{notification}/unread', [NotificationCenterController::class, 'markUnread'])
            ->middleware('can:notifications.view')->name('unread');
        Route::post('/{notification}/snooze', [NotificationCenterController::class, 'snooze'])
            ->middleware('can:notifications.view')->name('snooze');
        Route::delete('/{notification}', [NotificationCenterController::class, 'dismiss'])
            ->middleware('can:notifications.view')->name('dismiss');
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
        Route::get('/', [IntegratedReportController::class, 'index'])
            ->middleware('can:reports.view')
            ->name('index');
        Route::get('/export', [IntegratedReportController::class, 'export'])
            ->middleware('can:reports.export')
            ->name('export');
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
        Route::post('/{legacyImport}/target', [LegacyImportController::class, 'updateTarget'])
            ->middleware('can:imports.validate')
            ->name('target.update');
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
