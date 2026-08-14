<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use Illuminate\Support\Facades\Route;

// ── Public ───────────────────────────────────────────────────────────────────
Route::get('/', fn() => view('landing'))->name('landing');

// ── Auth ──────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login',    [LoginController::class, 'login'])->name('login.post');
    Route::get('/register',  [RegisterController::class, 'showRegister'])->name('register');
    // Cap signups per IP so nobody can script thousands of throwaway tenants.
    Route::post('/register', [RegisterController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.post');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ── Kasir: masuk dengan PIN (tablet) ─────────────────────────────────────────
// Sengaja di luar grup 'business': perangkat belum punya sesi sampai PIN benar.
Route::get('/kasir',          [PinLoginController::class, 'show'])->name('pin.show');
Route::post('/kasir/pair',    [PinLoginController::class, 'pair'])->name('pin.pair')->middleware('throttle:20,1');
Route::post('/kasir/unpair',  [PinLoginController::class, 'unpair'])->name('pin.unpair');
Route::post('/kasir/login',   [PinLoginController::class, 'login'])->name('pin.login')->middleware('throttle:30,1');
Route::post('/kasir/logout',  [PinLoginController::class, 'logout'])->name('pin.logout');

// ── Authenticated + Business ──────────────────────────────────────────────────
Route::middleware(['auth', 'business'])->group(function () {

    if (config('pos.features.subscription')) {
        Route::get('/subscription/expired', [SubscriptionController::class, 'expired'])->name('subscription.expired');
    }

});

Route::middleware(['auth', 'business', 'subscription'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Outlets
    Route::resource('outlets', OutletController::class);

    // Users
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::get('profile',  [UserController::class, 'profile'])->name('profile');
    Route::post('profile', [UserController::class, 'updateProfile'])->name('profile.update');

    // Categories
    Route::resource('categories', ProductCategoryController::class);

    // Products
    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

    // Product Bundles
    Route::resource('bundles', ProductBundleController::class)->except('show');
    Route::patch('bundles/{bundle}/toggle', [ProductBundleController::class, 'toggle'])->name('bundles.toggle');
    Route::post('products/{product}/variants',           [ProductController::class, 'storeVariant'])->name('products.variants.store');
    Route::delete('products/{product}/variants/{variant}',[ProductController::class, 'destroyVariant'])->name('products.variants.destroy');
    Route::post('products/{product}/addons',             [ProductController::class, 'storeAddon'])->name('products.addons.store');
    Route::delete('products/{product}/addons/{addon}',   [ProductController::class, 'destroyAddon'])->name('products.addons.destroy');

    // Shifts
    Route::get('shifts',              [CashierShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/open',         [CashierShiftController::class, 'open'])->name('shifts.open');
    Route::post('shifts/open',        [CashierShiftController::class, 'store'])->name('shifts.store');
    Route::get('shifts/{shift}/close',[CashierShiftController::class, 'close'])->name('shifts.close');
    Route::post('shifts/{shift}/close',[CashierShiftController::class,'closeStore'])->name('shifts.close.store');
    Route::get('shifts/{shift}',      [CashierShiftController::class, 'show'])->name('shifts.show');

    // POS
    Route::get('pos',                               [PosController::class, 'index'])->name('pos.index');
    // Dipanggil lewat fetch() dari layar POS — selalu balas JSON, termasuk saat
    // validasi gagal, supaya kasir melihat pesan asli bukan galat parsing.
    Route::middleware('json')->group(function () {
        Route::post('pos/order',                     [PosController::class, 'store'])->name('pos.store');
        Route::post('pos/qris/dynamic',              [PosController::class, 'dynamicQris'])->name('pos.qris.dynamic');
        Route::post('pos/qris-draft',                [PosController::class, 'createQrisDraft'])->name('pos.qris-draft');
        Route::post('pos/qris-confirm/{order}',      [PosController::class, 'confirmQrisManual'])->name('pos.qris-confirm');
        Route::post('pos/hold',                      [PosController::class, 'hold'])->name('pos.hold');
        Route::get('pos/drafts',                     [PosController::class, 'drafts'])->name('pos.drafts');
        Route::post('pos/drafts/{order}/load',       [PosController::class, 'loadDraft'])->name('pos.drafts.load');
        Route::get('pos/customer/{customer}/points', [PosController::class, 'customerPoints'])->name('pos.customer.points');
    });

    // Orders
    Route::get('orders',                 [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}',         [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/void',   [OrderController::class, 'void'])->name('orders.void');
    Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

    // Receipt
    Route::get('receipt/{order}',          [ReceiptController::class, 'show'])->name('receipt.show');
    Route::get('receipt/{order}/print',    [ReceiptController::class, 'print'])->name('receipt.print');
    Route::get('receipt/{order}/pdf',      [ReceiptController::class, 'pdf'])->name('receipt.pdf');

    // Inventory
    Route::resource('inventory/ingredients', IngredientController::class)->names([
        'index'   => 'ingredients.index',
        'create'  => 'ingredients.create',
        'store'   => 'ingredients.store',
        'show'    => 'ingredients.show',
        'edit'    => 'ingredients.edit',
        'update'  => 'ingredients.update',
        'destroy' => 'ingredients.destroy',
    ]);
    Route::post('inventory/ingredients/{ingredient}/stock-in',   [IngredientController::class, 'stockIn'])->name('ingredients.stock-in');
    Route::post('inventory/ingredients/{ingredient}/stock-out',  [IngredientController::class, 'stockOut'])->name('ingredients.stock-out');
    Route::post('inventory/ingredients/{ingredient}/adjustment', [IngredientController::class, 'adjustment'])->name('ingredients.adjustment');
    Route::get('inventory/movements', [IngredientController::class, 'movements'])->name('inventory.movements');

    // Recipes
    Route::get('recipes',          [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/{product}',[RecipeController::class, 'edit'])->name('recipes.edit');
    Route::post('recipes/{product}',[RecipeController::class,'update'])->name('recipes.update');

    // Kitchen
    Route::get('kitchen',  [KitchenController::class, 'index'])->name('kitchen.index')
        ->middleware('role:owner,admin,kitchen');
    Route::patch('kitchen/orders/{order}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.status');

    // Customers
    Route::resource('customers', CustomerController::class);

    // Promotions
    Route::resource('promotions', PromotionController::class);
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('sales',            [ReportController::class, 'sales'])->name('sales');
        Route::get('products',         [ReportController::class, 'products'])->name('products');
        Route::get('cashier',          [ReportController::class, 'cashier'])->name('cashier');
        Route::get('shift',            [ReportController::class, 'shift'])->name('shift');
        Route::get('inventory',        [ReportController::class, 'inventory'])->name('inventory');
        Route::get('profit',           [ReportController::class, 'profit'])->name('profit');
        // Excel exports
        Route::get('sales/export',     [ReportController::class, 'exportSales'])->name('sales.export');
        Route::get('products/export',  [ReportController::class, 'exportProducts'])->name('products.export');
        Route::get('shift/export',     [ReportController::class, 'exportShifts'])->name('shift.export');
        // PDF exports
        Route::get('sales/pdf',        [ReportController::class, 'exportSalesPdf'])->name('sales.pdf');
        Route::get('products/pdf',     [ReportController::class, 'exportProductsPdf'])->name('products.pdf');
        Route::get('profit/pdf',       [ReportController::class, 'exportProfitPdf'])->name('profit.pdf');
    });

    // Audit Log
    if (config('pos.features.audit_log')) {
        Route::get('audit-log', [ActivityLogController::class, 'index'])->name('audit.index')
            ->middleware('role:owner,admin');
    }

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('business',  [SettingController::class, 'business'])->name('business');
        Route::post('business', [SettingController::class, 'updateBusiness'])->name('business.update');
        Route::get('outlet',    [SettingController::class, 'outlet'])->name('outlet');
        Route::post('outlet',   [SettingController::class, 'updateOutlet'])->name('outlet.update');
        Route::get('receipt',   [SettingController::class, 'receipt'])->name('receipt');
        Route::post('receipt',  [SettingController::class, 'updateReceipt'])->name('receipt.update');
        Route::get('qris',      [SettingController::class, 'qris'])->name('qris');
        Route::post('qris',     [SettingController::class, 'updateQris'])->name('qris.update');
    });

    // SaaS
    if (config('pos.features.subscription')) {
        Route::prefix('saas')->name('saas.')->middleware('role:owner')->group(function () {
            Route::get('plans',                [SubscriptionController::class, 'plans'])->name('plans');
            Route::get('subscription',         [SubscriptionController::class, 'current'])->name('current');
            Route::post('subscription/{plan}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        });
    }

    // Midtrans — QRIS dynamic
    Route::prefix('midtrans')->name('midtrans.')->group(function () {
        Route::post('qris/create',         [MidtransController::class, 'createQris'])->name('qris.create');
        Route::post('qris/status',         [MidtransController::class, 'checkQrisStatus'])->name('qris.status');
        Route::get('subscription/{plan}',  [MidtransController::class, 'createSubscriptionPayment'])->name('subscription');
    });

    // Barcode
    Route::prefix('barcodes')->name('barcodes.')->group(function () {
        Route::get('select',               [BarcodeController::class, 'massSelect'])->name('select');
        Route::get('print',                [BarcodeController::class, 'printLabels'])->name('print');
        Route::get('product/{product}',    [BarcodeController::class, 'show'])->name('show');
    });

    // Merchant Balance & Withdrawal
    if (config('pos.features.balance')) {
        Route::prefix('balance')->name('balance.')->middleware('role:owner')->group(function () {
            Route::get('/',                          [BalanceController::class, 'index'])->name('index');
            Route::post('withdraw',                  [BalanceController::class, 'requestWithdrawal'])->name('withdraw');
            Route::delete('withdraw/{wd}',           [BalanceController::class, 'cancelWithdrawal'])->name('cancel-withdrawal');
        });

        // Admin: Withdrawal Management
        Route::prefix('admin/withdrawals')->name('admin.withdrawals.')->middleware('role:owner')->group(function () {
            Route::get('/',                          [WithdrawalAdminController::class, 'index'])->name('index');
            Route::patch('{wd}/approve',             [WithdrawalAdminController::class, 'approve'])->name('approve');
            Route::patch('{wd}/complete',            [WithdrawalAdminController::class, 'complete'])->name('complete');
            Route::patch('{wd}/reject',              [WithdrawalAdminController::class, 'reject'])->name('reject');
        });
    }
});

// Midtrans webhook — no auth, no CSRF (called by Midtrans server)
Route::post('/midtrans/callback', [MidtransController::class, 'callback'])
    ->name('midtrans.callback')
    ->withoutMiddleware(['web']);
