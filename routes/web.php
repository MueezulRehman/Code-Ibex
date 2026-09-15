<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\DealController;
use App\Http\Controllers\Admin\ProductVariantController;
use App\Http\Controllers\Admin\VariantAttributeController;
use App\Http\Controllers\Admin\BusinessTypeController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ToppingController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\RestaurantSubscriptionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\FeedbackController as AdminFeedbackController;
use App\Http\Controllers\Customer\FeedbackController as CustomerFeedbackController;
use App\Http\Controllers\Admin\CashbookController;
use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\AttendanceController;
use App\Http\Controllers\Admin\SalaryController;
use App\Http\Controllers\Admin\RestaurantController;
use App\Http\Controllers\Admin\RestaurantProfileController;
use App\Http\Controllers\Admin\RestaurantThemeController;
use App\Http\Controllers\Admin\ManagerAuthController;
use App\Http\Controllers\Admin\ManagerDashboardController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\ManagerFeedbackController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\Admin\DeliveryController;
use App\Http\Controllers\Admin\PlatformSettingsController;
use App\Http\Controllers\Admin\StockAnalysisController;
use App\Http\Controllers\Admin\ItemSaleController;
use App\Http\Middleware\EnsureRestaurantManager;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\AuthenticateManager;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureCeo;
use App\Http\Middleware\EnsureCeoBusinessAccess;
use App\Http\Middleware\EnsureCeoBranchAccess;
use App\Http\Controllers\Ceo\AuthController as CeoAuthController;
use App\Http\Controllers\Ceo\DashboardController as CeoDashboardController;
use App\Http\Controllers\Ceo\BusinessController as CeoBusinessController;
use App\Http\Controllers\Ceo\BranchController as CeoBranchController;
use App\Http\Controllers\Ceo\ReportController as CeoReportController;
use App\Http\Controllers\Ceo\AlertController as CeoAlertController;
use App\Http\Controllers\Ceo\ProfileController as CeoProfileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderTrackingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CodeIbex platform homepage; business storefronts use /{slug}
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/privacy-policy', 'customer.privacy')->name('privacy');
Route::view('/terms', 'customer.terms')->name('terms');
Route::view('/faq', 'customer.faq')->name('faq');

/*
|--------------------------------------------------------------------------
| Customer order tracking
|--------------------------------------------------------------------------
*/
Route::get('/track/{tracking_token}', [OrderTrackingController::class, 'show'])->name('orders.track');
Route::get('/queue-token/{restaurantSlug}/{publicToken}', [App\Http\Controllers\Admin\MedicalQueueController::class, 'publicToken'])->name('medical-token.show');
Route::get('/queue-display/{restaurantSlug}', [App\Http\Controllers\Admin\MedicalQueueController::class, 'publicDisplay'])->name('medical-queue.display');
Route::get('/track', fn() => view('customer.lookup'))->name('orders.lookup.form');
Route::post('/track/lookup', [OrderTrackingController::class, 'lookup'])->middleware('throttle:10,1')->name('orders.lookup');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:20,1')->name('checkout.store');

/*
|--------------------------------------------------------------------------
| Customer accounts (optional — guest checkout always still works)
|--------------------------------------------------------------------------
*/
Route::middleware('guest:customer')->group(function () {
    Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('customer.register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:5,10')->name('customer.register.attempt');
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('customer.login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:5,10')->name('customer.login.attempt');
});

Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('customer.logout');
    Route::get('/account', [CustomerDashboardController::class, 'index'])->name('account.dashboard');

    // Customer Feedback
    Route::get('/feedback', [CustomerFeedbackController::class, 'index'])->name('customer.feedback.index');
    Route::get('/feedback/create', [CustomerFeedbackController::class, 'create'])->name('customer.feedback.create');
    Route::post('/feedback', [CustomerFeedbackController::class, 'store'])->middleware('throttle:10,10')->name('customer.feedback.store');
    Route::get('/feedback/{feedback}', [CustomerFeedbackController::class, 'show'])->name('customer.feedback.show');
});

/*
|--------------------------------------------------------------------------
| CEO executive panel
|--------------------------------------------------------------------------
*/
Route::prefix('ceo')->name('ceo.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [CeoAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [CeoAuthController::class, 'login'])->middleware('throttle:5,10')->name('login.attempt');
    });

    Route::middleware(['auth', EnsureCeo::class])->group(function () {
        Route::post('/logout', [CeoAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [CeoDashboardController::class, 'index'])->name('dashboard');
    });
});

/*
|--------------------------------------------------------------------------
| Super Admin panel
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {

    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,10')->name('login.attempt');

    Route::middleware([AuthenticateAdmin::class, EnsureSuperAdmin::class])->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/feed', [\App\Http\Controllers\Admin\NotificationController::class, 'feed'])->name('notifications.feed');
        Route::match(['get', 'post'], '/notifications/{notification}/read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');

        // Stock analysis
        Route::get('/stock-analysis', [StockAnalysisController::class, 'adminIndex'])->name('stock-analysis.index');
        Route::get('/stock-analysis/export', [StockAnalysisController::class, 'adminExport'])->name('stock-analysis.export');

        // Platform feedback
        Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
        Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
        Route::post('/feedback/{feedback}/reply', [AdminFeedbackController::class, 'reply'])->name('feedback.reply');
        Route::patch('/feedback/{feedback}/status', [AdminFeedbackController::class, 'updateStatus'])->name('feedback.update-status');
        Route::delete('/feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('feedback.destroy');

        // Business Types (super admin only)
        Route::resource('/business-types', BusinessTypeController::class)->except(['show']);

        // Modules (super admin only)
        Route::resource('/modules', ModuleController::class)->except(['show']);

        // Subscription Plans (super admin only)
        Route::resource('/subscription-plans', SubscriptionPlanController::class)->except(['show']);

        // Restaurants CRUD (super admin only)
        Route::resource('/restaurants', RestaurantController::class)->except(['show']);
        Route::post('/restaurants/{restaurant}/enter', [RestaurantController::class, 'enter'])->name('restaurants.enter');
        Route::post('/restaurants/exit', [RestaurantController::class, 'exit'])->name('restaurants.exit');
        Route::get('/restaurants/{restaurant}/manager-access', [RestaurantController::class, 'managerAccess'])->name('restaurants.manager-access');
        Route::patch('/restaurants/{restaurant}/manager-access/{manager}', [RestaurantController::class, 'updateManagerAccess'])->name('restaurants.manager-access.update');
        Route::get('/restaurants/{restaurant}/ceo-access', [RestaurantController::class, 'ceoAccess'])->name('restaurants.ceo-access');
        Route::post('/restaurants/{restaurant}/ceo-access', [RestaurantController::class, 'assignCeo'])->name('restaurants.ceo-access.assign');

        // My Account — super admin's own login name/email/phone/password
        Route::get('/account', [App\Http\Controllers\Admin\AccountController::class, 'edit'])->name('account.edit');
        Route::patch('/account', [App\Http\Controllers\Admin\AccountController::class, 'update'])->name('account.update');
        Route::patch('/account/password', [App\Http\Controllers\Admin\AccountController::class, 'updatePassword'])->name('account.password');
        Route::get('/platform-settings', [PlatformSettingsController::class, 'edit'])->name('platform.settings');
        Route::put('/platform-settings', [PlatformSettingsController::class, 'update'])->name('platform.settings.update');
    });
});

/*
|--------------------------------------------------------------------------
| Restaurant Manager panel (separate login at /manager/login)
|--------------------------------------------------------------------------
*/
Route::prefix('manager')->name('manager.')->group(function () {

    Route::get('/login', [ManagerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ManagerAuthController::class, 'login'])->middleware('throttle:5,10')->name('login.attempt');

    Route::get('/subscription-expired', [ManagerDashboardController::class, 'subscriptionExpired'])->name('subscription.expired');

    Route::middleware(['auth'])->group(function () {
        Route::post('/logout', [ManagerAuthController::class, 'logout'])->name('logout');
    });

    Route::middleware(['auth', EnsureCeo::class])->group(function () {
        Route::get('/ceo-dashboard', [CeoDashboardController::class, 'index'])->name('ceo.dashboard');
        Route::get('/ceo/businesses', [CeoBusinessController::class, 'index'])->name('ceo.businesses.index');
        Route::get('/ceo/businesses/{business}', [CeoBusinessController::class, 'show'])
            ->middleware(EnsureCeoBusinessAccess::class)
            ->name('ceo.businesses.show');
        Route::get('/ceo/branches', [CeoBranchController::class, 'index'])->name('ceo.branches.index');
        Route::get('/ceo/businesses/{business}/branches/{branch}', [CeoBranchController::class, 'show'])
            ->middleware([EnsureCeoBusinessAccess::class, EnsureCeoBranchAccess::class])
            ->name('ceo.branches.show');
        Route::get('/ceo/reports', [CeoReportController::class, 'index'])
            ->name('ceo.reports.index');
        Route::get('/ceo/alerts', [CeoAlertController::class, 'index'])
            ->name('ceo.alerts.index');
        Route::get('/ceo/profile', [CeoProfileController::class, 'index'])->name('ceo.profile');
    });

    Route::middleware([AuthenticateManager::class, EnsureRestaurantManager::class, EnsureSubscriptionActive::class])->group(function () {
        Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');

        // Stock analysis
        Route::get('/stock-analysis', [StockAnalysisController::class, 'managerIndex'])->name('stock-analysis.index');

        // Manager sees their own restaurant's data via admin routes scoped to their restaurant_id
        Route::middleware('module:manager-theme,theme')->group(function () {
            Route::get('/restaurant/profile', [RestaurantProfileController::class, 'edit'])->name('restaurant.profile.edit');
            Route::patch('/restaurant/profile', [RestaurantProfileController::class, 'update'])->name('restaurant.profile.update');
            Route::get('/business/theme', [RestaurantThemeController::class, 'edit'])->name('business.theme.edit');
            Route::patch('/business/theme', [RestaurantThemeController::class, 'update'])->name('business.theme.update');
            Route::redirect('/restaurant/theme', '/manager/business/theme')->name('restaurant.theme.legacy');
        });

        Route::middleware('module:customer-theme,storefront-notices,theme')->group(function () {
            Route::get('/storefront-notice', [\App\Http\Controllers\Admin\StorefrontNoticeController::class, 'edit'])->name('storefront-notice.edit');
            Route::patch('/storefront-notice', [\App\Http\Controllers\Admin\StorefrontNoticeController::class, 'update'])->name('storefront-notice.update');
            Route::delete('/storefront-notice', [\App\Http\Controllers\Admin\StorefrontNoticeController::class, 'destroy'])->name('storefront-notice.destroy');
        });

        // My Account — manager's own login name/email/phone/password
        Route::get('/account', [App\Http\Controllers\Admin\AccountController::class, 'edit'])->name('account.edit');
        Route::patch('/account', [App\Http\Controllers\Admin\AccountController::class, 'update'])->name('account.update');
        Route::patch('/account/password', [App\Http\Controllers\Admin\AccountController::class, 'updatePassword'])->name('account.password');

        // Orders
        Route::middleware('module:orders')->group(function () {
            Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.status');
            Route::patch('/orders/{order}/payment', [AdminOrderController::class, 'updatePayment'])->name('orders.payment');
        });

        Route::middleware('module:delivery,delivery-dispatch')->group(function () {
            Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries.index');
            Route::patch('/deliveries/{delivery}', [DeliveryController::class, 'update'])->name('deliveries.update');
        });

        Route::middleware('module:reservations')->group(function () {
            Route::get('/reservations', [App\Http\Controllers\Admin\ReservationController::class, 'index'])->name('reservations.index');
            Route::get('/reservations/create', [App\Http\Controllers\Admin\ReservationController::class, 'create'])->name('reservations.create');
            Route::post('/reservations', [App\Http\Controllers\Admin\ReservationController::class, 'store'])->name('reservations.store');
            Route::patch('/reservations/{reservation}/status', [App\Http\Controllers\Admin\ReservationController::class, 'updateStatus'])->name('reservations.status');
        });

        Route::middleware('module:kitchen-display')->group(function () {
            Route::get('/kitchen-display', [App\Http\Controllers\Admin\KitchenTicketController::class, 'index'])->name('kitchen-display.index');
            Route::post('/kitchen-display/tickets', [App\Http\Controllers\Admin\KitchenTicketController::class, 'store'])->name('kitchen-display.tickets.store');
            Route::patch('/kitchen-display/tickets/{ticket}/status', [App\Http\Controllers\Admin\KitchenTicketController::class, 'updateStatus'])->name('kitchen-display.tickets.status');
        });

        Route::middleware('module:fitting-room')->group(function () {
            Route::get('/fitting-room', [App\Http\Controllers\Admin\FittingRoomController::class, 'index'])->name('fitting-room.index');
            Route::post('/fitting-room', [App\Http\Controllers\Admin\FittingRoomController::class, 'store'])->name('fitting-room.store');
            Route::patch('/fitting-room/{session}/status', [App\Http\Controllers\Admin\FittingRoomController::class, 'updateStatus'])->name('fitting-room.status');
        });

        // POS — Restaurant / Retail / Medical Store, view + logic switch on
        // business type automatically (see Restaurant::getPosMode()).
        Route::middleware('module:pos')->group(function () {
            Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
            Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
            Route::get('/pos/lookup', [PosController::class, 'lookup'])->name('pos.lookup');
            Route::get('/barcode-lookup', [\App\Http\Controllers\Admin\BarcodeLookupController::class, 'lookup'])->name('barcode.lookup');
            Route::post('/barcode-quick-store', [\App\Http\Controllers\Admin\BarcodeLookupController::class, 'quickStore'])->name('barcode.quick');
            Route::get('/pos/receipt/{order}', [PosController::class, 'receipt'])->name('pos.receipt');
            Route::get('/sales', [PosController::class, 'sales'])->name('sales.index');
        });

        Route::middleware('module:sales-returns')->group(function () {
            Route::get('/sales-returns', [App\Http\Controllers\Admin\SalesReturnController::class, 'index'])->name('sales-returns.index');
            Route::post('/sales-returns', [App\Http\Controllers\Admin\SalesReturnController::class, 'store'])->name('sales-returns.store');
        });

        Route::middleware('module:purchasing')->group(function () {
            Route::get('/purchasing', [App\Http\Controllers\Admin\InventoryPurchaseController::class, 'index'])->name('purchasing.index');
            Route::get('/purchasing/create', [App\Http\Controllers\Admin\InventoryPurchaseController::class, 'create'])->name('purchasing.create');
            Route::post('/purchasing', [App\Http\Controllers\Admin\InventoryPurchaseController::class, 'store'])->name('purchasing.store');
        });

        Route::middleware('module:expiry-tracking')->get('/expiry-tracking', [App\Http\Controllers\Admin\InventoryPurchaseController::class, 'expiryTracking'])->name('expiry-tracking.index');

        Route::middleware('module:delivery-zones')->group(function () {
            Route::resource('/delivery-zones', App\Http\Controllers\Admin\DeliveryZoneController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        Route::middleware('module:coupons')->group(function () {
            Route::resource('/coupons', App\Http\Controllers\Admin\CouponController::class)->only(['index', 'store', 'update', 'destroy']);
        });

        Route::middleware('module:suppliers')->group(function () {
            Route::resource('/suppliers', App\Http\Controllers\Admin\SupplierController::class)->except(['show']);
        });

        Route::middleware('module:warranty,repairs,service-tickets')->group(function () {
            Route::get('/service-cases', [App\Http\Controllers\Admin\ServiceCaseController::class, 'index'])->name('service-cases.index');
            Route::post('/service-cases', [App\Http\Controllers\Admin\ServiceCaseController::class, 'store'])->name('service-cases.store');
            Route::patch('/service-cases/{serviceCase}', [App\Http\Controllers\Admin\ServiceCaseController::class, 'update'])->name('service-cases.update');
            Route::post('/service-cases/{serviceCase}/notify-collection', [App\Http\Controllers\Admin\ServiceCaseController::class, 'notifyCollection'])->name('service-cases.notify-collection');
        });

        Route::middleware('module:barcode-labels')->get('/barcode-labels', [App\Http\Controllers\Admin\RetailToolsController::class, 'barcodeLabels'])->name('barcode-labels.index');
        Route::middleware('module:profit-margins')->get('/profit-margins', [App\Http\Controllers\Admin\RetailToolsController::class, 'profitMargins'])->name('profit-margins.index');
        Route::middleware('module:brands')->group(function () {
            Route::get('/retail-operations', [App\Http\Controllers\Admin\RetailOperationsController::class, 'index'])->name('retail-operations.index');
            Route::post('/retail-operations', [App\Http\Controllers\Admin\RetailOperationsController::class, 'store'])->name('retail-operations.store');
        });

        Route::middleware('module:wholesale-price-lists,sales-representatives')->group(function () {
            Route::get('/wholesale', [App\Http\Controllers\Admin\WholesaleController::class, 'index'])->name('wholesale.index');
            Route::get('/wholesale/commissions', [App\Http\Controllers\Admin\WholesaleController::class, 'commissionReport'])->name('wholesale.commissions');
            Route::post('/wholesale/price-lists', [App\Http\Controllers\Admin\WholesaleController::class, 'storePriceList'])->name('wholesale.price-lists.store');
            Route::post('/wholesale/price-list-items', [App\Http\Controllers\Admin\WholesaleController::class, 'storePriceListItem'])->name('wholesale.price-list-items.store');
            Route::post('/wholesale/representatives', [App\Http\Controllers\Admin\WholesaleController::class, 'storeRepresentative'])->name('wholesale.representatives.store');
            Route::patch('/wholesale/customers/representative', [App\Http\Controllers\Admin\WholesaleController::class, 'assignRepresentative'])->name('wholesale.customers.representative');
        });

        Route::middleware('module:collections')->group(function () {
            Route::get('/collections', [App\Http\Controllers\Admin\RetailCollectionController::class, 'index'])->name('collections.index');
            Route::post('/collections', [App\Http\Controllers\Admin\RetailCollectionController::class, 'store'])->name('collections.store');
            Route::patch('/collections/{collection}', [App\Http\Controllers\Admin\RetailCollectionController::class, 'update'])->name('collections.update');
            Route::post('/collections/{collection}/items', [App\Http\Controllers\Admin\RetailCollectionController::class, 'assignItem'])->name('collections.assign-item');
        });

        Route::middleware('module:stock-transfers')->group(function () {
            Route::get('/branches', [App\Http\Controllers\Admin\BranchController::class, 'index'])->name('branches.index');
            Route::post('/branches', [App\Http\Controllers\Admin\BranchController::class, 'store'])->name('branches.store');
            Route::patch('/branches/{branch}', [App\Http\Controllers\Admin\BranchController::class, 'update'])->name('branches.update');
            Route::get('/branch-inventory', [App\Http\Controllers\Admin\BranchInventoryController::class, 'index'])->name('branch-inventory.index');
            Route::post('/branch-inventory', [App\Http\Controllers\Admin\BranchInventoryController::class, 'adjust'])->name('branch-inventory.adjust');
            Route::get('/stock-transfers', [App\Http\Controllers\Admin\StockTransferController::class, 'index'])->name('stock-transfers.index');
            Route::post('/stock-transfers', [App\Http\Controllers\Admin\StockTransferController::class, 'store'])->name('stock-transfers.store');
            Route::patch('/stock-transfers/{stockTransfer}', [App\Http\Controllers\Admin\StockTransferController::class, 'update'])->name('stock-transfers.update');
        });

        Route::middleware('module:trade-ins')->group(function () {
            Route::get('/trade-ins', [App\Http\Controllers\Admin\TradeInController::class, 'index'])->name('trade-ins.index');
            Route::post('/trade-ins', [App\Http\Controllers\Admin\TradeInController::class, 'store'])->name('trade-ins.store');
            Route::patch('/trade-ins/{tradeIn}', [App\Http\Controllers\Admin\TradeInController::class, 'update'])->name('trade-ins.update');
        });

        Route::middleware('module:installments')->group(function () {
            Route::get('/installments', [App\Http\Controllers\Admin\InstallmentController::class, 'index'])->name('installments.index');
            Route::post('/installments', [App\Http\Controllers\Admin\InstallmentController::class, 'store'])->name('installments.store');
            Route::patch('/installments/{installmentPlan}', [App\Http\Controllers\Admin\InstallmentController::class, 'update'])->name('installments.update');
        });

        Route::middleware('module:loyalty')->group(function () {
            Route::get('/loyalty', [App\Http\Controllers\Admin\LoyaltyController::class, 'index'])->name('loyalty.index');
            Route::post('/loyalty', [App\Http\Controllers\Admin\LoyaltyController::class, 'store'])->name('loyalty.store');
            Route::post('/loyalty/{loyaltyAccount}/redeem', [App\Http\Controllers\Admin\LoyaltyController::class, 'redeem'])->name('loyalty.redeem');
        });

        Route::middleware('module:credit-sales')->group(function () {
            Route::get('/credit-sales', [App\Http\Controllers\Admin\CreditSalesController::class, 'index'])->name('credit-sales.index');
            Route::post('/credit-sales/{customer}/payment', [App\Http\Controllers\Admin\CreditSalesController::class, 'payment'])->name('credit-sales.payment');
        });

        Route::middleware('module:device-tracking')->group(function () {
            Route::get('/product-devices', [App\Http\Controllers\Admin\ProductDeviceController::class, 'index'])->name('product-devices.index');
            Route::post('/product-devices', [App\Http\Controllers\Admin\ProductDeviceController::class, 'store'])->name('product-devices.store');
            Route::patch('/product-devices/{productDevice}', [App\Http\Controllers\Admin\ProductDeviceController::class, 'update'])->name('product-devices.update');
        });

        // Categories — a manager needs the "categories" module grant to do
        // any category CRUD.
        Route::middleware('module:categories')->group(function () {
            Route::resource('/categories', CategoryController::class)->except(['show']);
        });

        // Menu Items + their variants/attributes — a manager needs the
        // "menu" module grant to do full CRUD here (create, edit, delete
        // menu items, sizes, variants and attributes).
        Route::middleware('module:menu')->group(function () {
            Route::resource('/menu-items', MenuItemController::class)->except(['show'])
                ->parameters(['menu-items' => 'item']);

            Route::resource('/toppings', ToppingController::class)->except(['show']);

            // Tables management for dine-in/table orders
            Route::middleware('module:tables')->group(function () {
                Route::resource('/tables', App\Http\Controllers\Admin\TableController::class)->except(['show']);
            });

            Route::middleware('module:variants')->group(function () {
                // Product Variants
                Route::get('/menu-items/{item}/variants', [ProductVariantController::class, 'index'])->name('menu-items.variants.index');
                Route::get('/menu-items/{item}/variants/create', [ProductVariantController::class, 'create'])->name('menu-items.variants.create');
                Route::post('/menu-items/{item}/variants', [ProductVariantController::class, 'store'])->name('menu-items.variants.store');
                Route::patch('/menu-items/{item}/variants/sizes/{size}', [ProductVariantController::class, 'updateSize'])->name('menu-items.variants.sizes.update');
                Route::delete('/menu-items/{item}/variants/sizes/{size}', [ProductVariantController::class, 'destroySize'])->name('menu-items.variants.sizes.destroy');
                Route::get('/menu-items/{item}/variants/{variant}/edit', [ProductVariantController::class, 'edit'])->name('menu-items.variants.edit');
                Route::patch('/menu-items/{item}/variants/{variant}', [ProductVariantController::class, 'update'])->name('menu-items.variants.update');
                Route::delete('/menu-items/{item}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('menu-items.variants.destroy');

                // Variant Attributes
                Route::get('/menu-items/{item}/attributes', [VariantAttributeController::class, 'index'])->name('menu-items.attributes.index');
                Route::get('/menu-items/{item}/attributes/create', [VariantAttributeController::class, 'create'])->name('menu-items.attributes.create');
                Route::post('/menu-items/{item}/attributes', [VariantAttributeController::class, 'store'])->name('menu-items.attributes.store');
                Route::get('/menu-items/{item}/attributes/{attribute}/edit', [VariantAttributeController::class, 'edit'])->name('menu-items.attributes.edit');
                Route::patch('/menu-items/{item}/attributes/{attribute}', [VariantAttributeController::class, 'update'])->name('menu-items.attributes.update');
                Route::delete('/menu-items/{item}/attributes/{attribute}', [VariantAttributeController::class, 'destroy'])->name('menu-items.attributes.destroy');
            });
        });

        Route::middleware('module:item-sales')->group(function () {
            Route::get('/item-sales', [ItemSaleController::class, 'index'])->name('item-sales.index');
            Route::get('/item-sales/create', [ItemSaleController::class, 'create'])->name('item-sales.create');
            Route::post('/item-sales', [ItemSaleController::class, 'store'])->name('item-sales.store');
            Route::get('/item-sales/{item_sale}/edit', [ItemSaleController::class, 'edit'])->name('item-sales.edit');
            Route::put('/item-sales/{item_sale}', [ItemSaleController::class, 'update'])->name('item-sales.update');
            Route::delete('/item-sales/{item_sale}', [ItemSaleController::class, 'destroy'])->name('item-sales.destroy');
        });

        // Deals
        Route::middleware('module:deals')->group(function () {
            Route::resource('/deals', DealController::class)->except(['show']);
        });

        // Customers
        Route::middleware('module:customers')->group(function () {
            Route::resource('/customers', CustomerController::class)->only(['index', 'show', 'store']);
            Route::post('/customers/{customer}/remind', [CustomerController::class, 'remind'])->name('customers.remind');
            Route::post('/customers/{customer}/payment', [CustomerController::class, 'recordPayment'])->name('customers.payment');
            Route::get('/customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
            Route::post('/customers/{customer}/statement/email', [CustomerController::class, 'emailStatement'])->name('customers.statement.email');
            Route::post('/customers/{customer}/orders/{order}/email-receipt', [CustomerController::class, 'emailReceipt'])->name('customers.receipt.email');
        });

        Route::middleware('module:appointments')->group(function () {
            Route::resource('/appointments', App\Http\Controllers\Admin\AppointmentController::class)->only(['index', 'create', 'store', 'destroy']);
            Route::patch('/appointments/{appointment}/status', [App\Http\Controllers\Admin\AppointmentController::class, 'updateStatus'])->name('appointments.status');
        });

        Route::middleware('module:medical')->group(function () {
            Route::get('/medical-notifications', [App\Http\Controllers\Admin\RestaurantProfileController::class, 'editMedicalNotifications'])->name('medical-notifications.edit');
            Route::patch('/medical-notifications', [App\Http\Controllers\Admin\RestaurantProfileController::class, 'updateMedicalNotifications'])->name('medical-notifications.update');
            Route::get('/visits/{visit}/edit', [App\Http\Controllers\Admin\VisitController::class, 'edit'])->name('visits.edit');
            Route::get('/visits/{visit}/print', [App\Http\Controllers\Admin\VisitController::class, 'print'])->name('visits.print');
            Route::resource('/visits', App\Http\Controllers\Admin\VisitController::class)->only(['index', 'show']);
            Route::patch('/visits/{visit}', [App\Http\Controllers\Admin\VisitController::class, 'update'])->name('visits.update');
            Route::resource('/doctors', App\Http\Controllers\Admin\DoctorController::class)->except(['show']);
            Route::resource('/patients', App\Http\Controllers\Admin\PatientController::class)->except(['show']);
            Route::get('/patients/{patient}/history', [App\Http\Controllers\Admin\PatientController::class, 'history'])->name('patients.history');
            Route::get('/medical-queue', [App\Http\Controllers\Admin\MedicalQueueController::class, 'index'])->name('medical-queue.index');
            Route::get('/medical-queue/create', [App\Http\Controllers\Admin\MedicalQueueController::class, 'create'])->name('medical-queue.create');
            Route::post('/medical-queue', [App\Http\Controllers\Admin\MedicalQueueController::class, 'store'])->name('medical-queue.store');
            Route::get('/medical-queue/{queueEntry}/print', [App\Http\Controllers\Admin\MedicalQueueController::class, 'print'])->name('medical-queue.print');
            Route::post('/medical-queue/next', [App\Http\Controllers\Admin\MedicalQueueController::class, 'next'])->name('medical-queue.next');
            Route::patch('/medical-queue/{queueEntry}/status', [App\Http\Controllers\Admin\MedicalQueueController::class, 'status'])->name('medical-queue.status');
        });

        Route::middleware('module:hospital-departments')->group(function () {
            Route::resource('/departments', App\Http\Controllers\Admin\DepartmentController::class)->except(['show']);
        });
        Route::middleware('module:hospital-admissions')->group(function () {
            Route::get('/admissions', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'index'])->name('admissions.index');
            Route::get('/admissions/create', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'create'])->name('admissions.create');
            Route::post('/admissions', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'store'])->name('admissions.store');
            Route::patch('/admissions/{admission}/bed', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'assignBed'])->name('admissions.bed');
            Route::patch('/admissions/{admission}/discharge', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'discharge'])->name('admissions.discharge');
            Route::patch('/discharge-summaries/{summary}', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'updateDischargeSummary'])->name('admissions.discharge-summary.update');
            Route::patch('/admissions/{admission}/transfer', [App\Http\Controllers\Admin\HospitalAdmissionController::class, 'transfer'])->name('admissions.transfer');
        });
        Route::middleware('module:hospital-wards-beds')->group(function () {
            Route::get('/wards', [App\Http\Controllers\Admin\WardController::class, 'index'])->name('wards.index');
            Route::post('/wards', [App\Http\Controllers\Admin\WardController::class, 'store'])->name('wards.store');
            Route::post('/wards/{ward}/locations', [App\Http\Controllers\Admin\WardController::class, 'storeLocation'])->name('wards.locations.store');
            Route::patch('/ward-locations/{location}', [App\Http\Controllers\Admin\WardController::class, 'updateLocation'])->name('wards.locations.update');
            Route::delete('/ward-locations/{location}', [App\Http\Controllers\Admin\WardController::class, 'destroyLocation'])->name('wards.locations.destroy');
            Route::post('/wards/{ward}/beds', [App\Http\Controllers\Admin\WardController::class, 'storeBed'])->name('wards.beds.store');
        });
        Route::middleware('module:hospital-nursing')->group(function () {
            Route::get('/nursing', [App\Http\Controllers\Admin\NursingAssignmentController::class, 'index'])->name('nursing.index');
            Route::post('/nursing', [App\Http\Controllers\Admin\NursingAssignmentController::class, 'store'])->name('nursing.store');
            Route::patch('/nursing/{assignment}/reassign', [App\Http\Controllers\Admin\NursingAssignmentController::class, 'reassign'])->name('nursing.reassign');
            Route::patch('/nursing/{assignment}/end', [App\Http\Controllers\Admin\NursingAssignmentController::class, 'end'])->name('nursing.end');
        });
        Route::middleware('module:hospital-vital-signs')->group(function () {
            Route::get('/vitals', [App\Http\Controllers\Admin\VitalSignController::class, 'index'])->name('vitals.index');
            Route::post('/vitals', [App\Http\Controllers\Admin\VitalSignController::class, 'store'])->name('vitals.store');
            Route::patch('/vitals/{vital}', [App\Http\Controllers\Admin\VitalSignController::class, 'update'])->name('vitals.update');
        });
        Route::middleware('module:hospital-clinical-notes')->group(function () {
            Route::get('/hospital-clinical-notes', [App\Http\Controllers\Admin\HospitalClinicalNoteController::class, 'index'])->name('hospital-clinical-notes.index');
            Route::post('/hospital-clinical-notes', [App\Http\Controllers\Admin\HospitalClinicalNoteController::class, 'store'])->name('hospital-clinical-notes.store');
            Route::patch('/hospital-clinical-notes/{note}', [App\Http\Controllers\Admin\HospitalClinicalNoteController::class, 'update'])->name('hospital-clinical-notes.update');
            Route::patch('/hospital-clinical-notes/{note}/void', [App\Http\Controllers\Admin\HospitalClinicalNoteController::class, 'void'])->name('hospital-clinical-notes.void');
        });
        Route::middleware('module:hospital-laboratory')->group(function () {
            Route::get('/laboratory', [App\Http\Controllers\Admin\LaboratoryController::class, 'index'])->name('laboratory.index');
            Route::post('/laboratory/orders', [App\Http\Controllers\Admin\LaboratoryController::class, 'storeOrder'])->name('laboratory.orders.store');
            Route::post('/laboratory/orders/{order}/result', [App\Http\Controllers\Admin\LaboratoryController::class, 'storeResult'])->name('laboratory.results.store');
            Route::patch('/laboratory/results/{result}', [App\Http\Controllers\Admin\LaboratoryController::class, 'updateResult'])->name('laboratory.results.update');
        });
        Route::middleware('module:hospital-radiology')->group(function () {
            Route::get('/radiology', [App\Http\Controllers\Admin\RadiologyController::class, 'index'])->name('radiology.index');
            Route::post('/radiology/orders', [App\Http\Controllers\Admin\RadiologyController::class, 'storeOrder'])->name('radiology.orders.store');
            Route::post('/radiology/orders/{order}/result', [App\Http\Controllers\Admin\RadiologyController::class, 'storeResult'])->name('radiology.results.store');
            Route::patch('/radiology/results/{result}', [App\Http\Controllers\Admin\RadiologyController::class, 'updateResult'])->name('radiology.results.update');
        });
        Route::middleware('module:hospital-operating-theatre')->group(function () {
            Route::get('/operating-theatre', [App\Http\Controllers\Admin\OperatingTheatreController::class, 'index'])->name('operating-theatre.index');
            Route::post('/operating-theatre/rooms', [App\Http\Controllers\Admin\OperatingTheatreController::class, 'storeRoom'])->name('operating-theatre.rooms.store');
            Route::patch('/operating-theatre/rooms/{room}', [App\Http\Controllers\Admin\OperatingTheatreController::class, 'updateRoom'])->name('operating-theatre.rooms.update');
            Route::post('/operating-theatre/surgeries', [App\Http\Controllers\Admin\OperatingTheatreController::class, 'storeSurgery'])->name('operating-theatre.surgeries.store');
            Route::patch('/operating-theatre/surgeries/{surgery}', [App\Http\Controllers\Admin\OperatingTheatreController::class, 'updateSurgery'])->name('operating-theatre.surgeries.update');
        });
        Route::middleware('module:hospital-billing')->group(function () {
            Route::get('/hospital-billing', [App\Http\Controllers\Admin\HospitalBillingController::class, 'index'])->name('hospital-billing.index');
            Route::post('/hospital-billing', [App\Http\Controllers\Admin\HospitalBillingController::class, 'store'])->name('hospital-billing.store');
            Route::patch('/hospital-billing/{billing}', [App\Http\Controllers\Admin\HospitalBillingController::class, 'update'])->name('hospital-billing.update');
            Route::patch('/hospital-billing/{billing}/post', [App\Http\Controllers\Admin\HospitalBillingController::class, 'post'])->name('hospital-billing.post');
            Route::patch('/hospital-billing/{billing}/pay', [App\Http\Controllers\Admin\HospitalBillingController::class, 'pay'])->name('hospital-billing.pay');
            Route::patch('/hospital-billing/{billing}/void', [App\Http\Controllers\Admin\HospitalBillingController::class, 'void'])->name('hospital-billing.void');
        });
        Route::middleware('module:hospital-medication')->group(function () {
            Route::get('/hospital-medication', [App\Http\Controllers\Admin\HospitalMedicationController::class, 'index'])->name('hospital-medication.index');
            Route::post('/hospital-medication/orders', [App\Http\Controllers\Admin\HospitalMedicationController::class, 'storeOrder'])->name('hospital-medication.orders.store');
            Route::post('/hospital-medication/orders/{order}/administrations', [App\Http\Controllers\Admin\HospitalMedicationController::class, 'storeAdministration'])->name('hospital-medication.administrations.store');
            Route::patch('/hospital-medication/administrations/{administration}', [App\Http\Controllers\Admin\HospitalMedicationController::class, 'updateAdministration'])->name('hospital-medication.administrations.update');
        });

        Route::middleware('module:memberships')->group(function () {
            Route::get('/gym', [App\Http\Controllers\Admin\GymController::class, 'index'])->name('gym.index');
            Route::post('/gym/plans', [App\Http\Controllers\Admin\GymController::class, 'storePlan'])->name('gym.plans.store');
            Route::post('/gym/memberships', [App\Http\Controllers\Admin\GymController::class, 'storeMembership'])->name('gym.memberships.store');
            Route::post('/gym/memberships/{membership}/renew', [App\Http\Controllers\Admin\GymController::class, 'renew'])->name('gym.memberships.renew');
            Route::post('/gym/memberships/{membership}/check-in', [App\Http\Controllers\Admin\GymController::class, 'checkIn'])->name('gym.memberships.check-in');
            Route::post('/gym/trainers', [App\Http\Controllers\Admin\GymController::class, 'storeTrainer'])->name('gym.trainers.store');
            Route::post('/gym/trainer-schedules', [App\Http\Controllers\Admin\GymController::class, 'storeSchedule'])->name('gym.trainer-schedules.store');
            Route::patch('/gym/memberships/{membership}/trainer', [App\Http\Controllers\Admin\GymController::class, 'assignTrainer'])->name('gym.memberships.trainer');
        });

        Route::middleware('module:service-packages')->group(function () {
            Route::get('/service-packages', [App\Http\Controllers\Admin\ServicePackageController::class, 'index'])->name('service-packages.index');
            Route::post('/service-packages', [App\Http\Controllers\Admin\ServicePackageController::class, 'storePackage'])->name('service-packages.store');
            Route::post('/service-packages/purchases', [App\Http\Controllers\Admin\ServicePackageController::class, 'sellPackage'])->name('service-packages.purchases.store');
            Route::patch('/service-packages/purchases/{purchase}/consume', [App\Http\Controllers\Admin\ServicePackageController::class, 'consumeVisit'])->name('service-packages.purchases.consume');
        });

        Route::middleware('module:commissions')->group(function () {
            Route::get('/commissions', [App\Http\Controllers\Admin\CommissionController::class, 'index'])->name('commissions.index');
            Route::post('/commissions/rules', [App\Http\Controllers\Admin\CommissionController::class, 'storeRule'])->name('commissions.rules.store');
            Route::post('/commissions/{earning}/pay', [App\Http\Controllers\Admin\CommissionController::class, 'pay'])->name('commissions.pay');
        });

        // Medicines (medical module)
        Route::middleware('module:medical')->group(function () {
            Route::resource('/medicines', App\Http\Controllers\Admin\MedicineController::class)->except(['show']);
            Route::get('/purchases', [App\Http\Controllers\Admin\PurchaseController::class, 'index'])->name('purchases.index');
            Route::get('/purchases/create', [App\Http\Controllers\Admin\PurchaseController::class, 'create'])->name('purchases.create');
            Route::post('/purchases', [App\Http\Controllers\Admin\PurchaseController::class, 'store'])->name('purchases.store');
            Route::get('/prescriptions/{prescription}/print', [App\Http\Controllers\Admin\PrescriptionController::class, 'print'])->name('prescriptions.print');
            Route::post('/prescriptions/{prescription}/dispense', [App\Http\Controllers\Admin\PrescriptionController::class, 'dispense'])->name('prescriptions.dispense');
            Route::resource('/prescriptions', App\Http\Controllers\Admin\PrescriptionController::class)->except(['edit', 'update', 'delete']);
            Route::resource('/batch-recalls', App\Http\Controllers\Admin\BatchRecallController::class)->except(['edit', 'update']);
            Route::resource('/customer-allergies', App\Http\Controllers\Admin\CustomerAllergyController::class)->except(['show']);
            Route::resource('/patient-allergies', App\Http\Controllers\Admin\PatientAllergyController::class)->except(['show']);
            Route::resource('/medicine-interactions', App\Http\Controllers\Admin\MedicineInteractionController::class)->except(['show']);
            Route::get('/medical-records', [App\Http\Controllers\Admin\MedicalRecordController::class, 'index'])->name('medical-records.index');
            Route::post('/medical-records', [App\Http\Controllers\Admin\MedicalRecordController::class, 'store'])->name('medical-records.store');
            Route::get('/medical-reports', [App\Http\Controllers\Admin\MedicalReportController::class, 'index'])->name('medical-reports.index');
            Route::get('/medical-reports/notification-deliveries', [App\Http\Controllers\Admin\MedicalReportController::class, 'notificationDeliveries'])->name('medical-reports.notification-deliveries');
            Route::get('/medical-reports/top-medicines', [App\Http\Controllers\Admin\MedicalReportController::class, 'topMedicines'])->name('medical-reports.top-medicines');
            Route::get('/medical-reports/expiry-analysis', [App\Http\Controllers\Admin\MedicalReportController::class, 'expiryAnalysis'])->name('medical-reports.expiry-analysis');
            Route::get('/medical-reports/supplier-performance', [App\Http\Controllers\Admin\MedicalReportController::class, 'supplierPerformance'])->name('medical-reports.supplier-performance');
            Route::get('/medical-reports/margin-analysis', [App\Http\Controllers\Admin\MedicalReportController::class, 'marginAnalysis'])->name('medical-reports.margin-analysis');
            Route::get('/medical-reports/revenue-trends', [App\Http\Controllers\Admin\MedicalReportController::class, 'revenueTrends'])->name('medical-reports.revenue-trends');
            Route::get('/medical-reports/inventory-audit-trail', [App\Http\Controllers\Admin\MedicalReportController::class, 'inventoryAuditTrail'])->name('medical-reports.inventory-audit-trail');
        });

        Route::middleware('module:controlled-medicines')->group(function () {
            Route::get('/controlled-medicines', [App\Http\Controllers\Admin\ControlledMedicineController::class, 'index'])->name('controlled-medicines.index');
            Route::post('/controlled-medicines', [App\Http\Controllers\Admin\ControlledMedicineController::class, 'store'])->name('controlled-medicines.store');
        });

        Route::middleware('module:insurance')->group(function () {
            Route::get('/insurance', [App\Http\Controllers\Admin\InsuranceController::class, 'index'])->name('insurance.index');
            Route::post('/insurance/providers', [App\Http\Controllers\Admin\InsuranceController::class, 'storeProvider'])->name('insurance.providers.store');
            Route::post('/insurance/claims', [App\Http\Controllers\Admin\InsuranceController::class, 'storeClaim'])->name('insurance.claims.store');
        });

        Route::middleware('module:follow-up-reminders')->group(function () {
            Route::get('/follow-up-reminders', [App\Http\Controllers\Admin\FollowUpReminderController::class, 'index'])->name('follow-up-reminders.index');
            Route::post('/follow-up-reminders', [App\Http\Controllers\Admin\FollowUpReminderController::class, 'store'])->name('follow-up-reminders.store');
            Route::patch('/follow-up-reminders/{followUpReminder}', [App\Http\Controllers\Admin\FollowUpReminderController::class, 'update'])->name('follow-up-reminders.update');
        });

        Route::middleware('module:custom-fields,custom-workflows')->group(function () {
            Route::get('/customization', [App\Http\Controllers\Admin\CustomizationController::class, 'index'])->name('customization.index');
            Route::post('/customization/fields', [App\Http\Controllers\Admin\CustomizationController::class, 'storeField'])->name('customization.fields.store');
            Route::post('/customization/workflows', [App\Http\Controllers\Admin\CustomizationController::class, 'storeWorkflow'])->name('customization.workflows.store');
        });

        Route::middleware('module:recipes,production-batches')->group(function () {
            Route::get('/recipes', [App\Http\Controllers\Admin\RecipeController::class, 'index'])->name('recipes.index');
            Route::post('/recipes', [App\Http\Controllers\Admin\RecipeController::class, 'store'])->name('recipes.store');
            Route::post('/recipes/ingredients', [App\Http\Controllers\Admin\RecipeController::class, 'storeIngredient'])->name('recipes.ingredients.store');
            Route::post('/recipes/produce', [App\Http\Controllers\Admin\RecipeController::class, 'produce'])->name('recipes.produce');
            Route::post('/recipes/wastage', [App\Http\Controllers\Admin\RecipeController::class, 'wastage'])->name('recipes.wastage');
        });

        // Cashbook
        Route::middleware('module:cashbook')->group(function () {
            Route::get('/cashbook', [CashbookController::class, 'index'])->name('cashbook.index');
            Route::get('/cashbook/create', [CashbookController::class, 'create'])->name('cashbook.create');
            Route::post('/cashbook', [CashbookController::class, 'store'])->name('cashbook.store');
            Route::delete('/cashbook/{cashbook}', [CashbookController::class, 'destroy'])->name('cashbook.destroy');
        });

        // Expenses
        Route::middleware('module:expenses')->group(function () {
            Route::resource('/expenses', ExpenseController::class)->except(['show']);
        });

        // Staff management is available to restaurant admins and managers.
        // StaffController limits a manager's module grants to the modules
        // that manager can already access.
        Route::middleware('restaurant.admin')->group(function () {
            Route::resource('/staff', StaffController::class)->except(['show'])
                ->parameters(['staff' => 'staff']);
        });

        // HR module: attendance and payroll management.
        Route::middleware('module:hr')->group(function () {
            // Attendance
            Route::middleware('module:attendance')->group(function () {
                Route::resource('/attendance', AttendanceController::class)->except(['show']);
            });

            // Salary
            Route::middleware('module:salary')->group(function () {
                Route::resource('/salary', SalaryController::class)->except(['show']);
            });
        });

        // Reports
        Route::middleware('module:reports')->group(function () {
            Route::resource('/reports', ReportController::class)->except(['edit', 'update']);
            Route::get('/reports/{report}/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
            Route::get('/reports/{report}/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');
        });

        // Stock Management (available in all modes: restaurant, retail, medical)
        Route::middleware('module:stock')->group(function () {
            Route::get('/stock', [App\Http\Controllers\Admin\StockController::class, 'index'])->name('stock.index');
            Route::post('/stock/adjust', [App\Http\Controllers\Admin\StockController::class, 'adjust'])->name('stock.adjust');
            Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock.adjustments.index');
            Route::get('/stock-adjustments/{adjustment}/edit', [StockAdjustmentController::class, 'edit'])->name('stock.adjustments.edit');
            Route::patch('/stock-adjustments/{adjustment}', [StockAdjustmentController::class, 'update'])->name('stock.adjustments.update');
            Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock.adjustments.store');
        });

        // Feedback
        Route::middleware('module:feedback')->group(function () {
            Route::get('/feedback', [AdminFeedbackController::class, 'index'])->name('feedback.index');
            Route::get('/feedback/{feedback}', [AdminFeedbackController::class, 'show'])->name('feedback.show');
            Route::post('/feedback/{feedback}/reply', [AdminFeedbackController::class, 'reply'])->name('feedback.reply');
            Route::patch('/feedback/{feedback}/status', [AdminFeedbackController::class, 'updateStatus'])->name('feedback.update-status');
            Route::delete('/feedback/{feedback}', [AdminFeedbackController::class, 'destroy'])->name('feedback.destroy');
            Route::get('/manager-feedback', [ManagerFeedbackController::class, 'index'])->name('manager.feedback.index');
            Route::post('/manager-feedback', [ManagerFeedbackController::class, 'store'])->name('manager.feedback.store');
            Route::get('/manager-feedback/{feedback}', [ManagerFeedbackController::class, 'show'])->name('manager.feedback.show');
        });

        Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/feed', [\App\Http\Controllers\Admin\NotificationController::class, 'feed'])->name('notifications.feed');
        Route::match(['get', 'post'], '/notifications/{notification}/read', [\App\Http\Controllers\Admin\NotificationController::class, 'markAsRead'])->name('notifications.read');
        Route::post('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'store'])->name('notifications.store');



        // Restaurant Subscriptions (for restaurant admins to view and pay their subscription)
        Route::get('/subscription', [RestaurantSubscriptionController::class, 'show'])->name('subscription.show');
        Route::post('/subscription/pay', [RestaurantSubscriptionController::class, 'pay'])->name('subscription.pay');
        Route::post('/subscription/cancel', [RestaurantSubscriptionController::class, 'cancel'])->name('subscription.cancel');
        Route::post('/subscription/reactivate', [RestaurantSubscriptionController::class, 'reactivate'])->name('subscription.reactivate');
    });
});

/*
|--------------------------------------------------------------------------
| Per-restaurant public menu pages  /{slug}
| MUST be last so it doesn't swallow /admin, /manager, /track etc.
|--------------------------------------------------------------------------
*/
Route::get('/{slug}', [MenuController::class, 'showBySlug'])
    ->name('menu.restaurant')
    ->where('slug', '^(?!admin|manager|ceo|track|checkout|register|login|logout|account|_debugbar)[a-z0-9\-]+$');
