<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\WithdrawalAdminController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PinLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BalanceController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\CashierShiftController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeatureController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\IntelligenceController;
use App\Http\Controllers\KitchenController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\Platform\ActivityController as PlatformActivityController;
use App\Http\Controllers\Platform\AuthController as PlatformAuthController;
use App\Http\Controllers\Platform\BusinessController as PlatformBusinessController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductBundleController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Public ───────────────────────────────────────────────────────────────────
Route::get('/', fn () => view('landing'))->name('landing');
Route::get('/fitur/{feature}', [FeatureController::class, 'show'])->name('features.show');

// ── Auth ──────────────────────────────────────────────────────────────────────
// Webhook Telegram: tanpa sesi/login, diverifikasi lewat secret header.
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])->name('telegram.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
    Route::get('/auth/google', [GoogleLoginController::class, 'redirect'])
        ->middleware('throttle:10,1')
        ->name('auth.google');
    Route::get('/auth/google/callback', [GoogleLoginController::class, 'callback'])
        ->middleware('throttle:20,1')
        ->name('auth.google.callback');
    Route::get('/auth/google/daftar', [GoogleLoginController::class, 'showRegistration'])
        ->name('auth.google.register');
    Route::post('/auth/google/daftar', [GoogleLoginController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('auth.google.register.store');
    Route::get('/register', [RegisterController::class, 'showRegister'])->name('register');
    // Cap signups per IP so nobody can script thousands of throwaway tenants.
    Route::post('/register', [RegisterController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register.post');

    // Lupa password: kirim tautan ke email, lalu atur password baru.
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1')->name('password.email');
});

// Tautan reset dari email harus bisa dibuka juga oleh pengguna yang sedang login (ganti password dari Profil).
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('throttle:10,1')->name('password.update');

// ── Konfirmasi email ─────────────────────────────────────────────────────────
// Tautan di email bertanda tangan dan bisa dibuka dari perangkat lain,
// jadi route verify tidak mewajibkan sesi login.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:20,1'])->name('verification.verify');
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:3,1')->name('verification.send');
});

// Akun baru menyiapkan tenant hanya setelah alamat emailnya terbukti valid.
Route::middleware(['auth', 'owner.verified'])->prefix('mulai')->name('onboarding.')->group(function () {
    Route::get('/bisnis', [OnboardingController::class, 'show'])->name('business');
    Route::post('/bisnis', [OnboardingController::class, 'store'])->name('business.store');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ── Backoffice tim Yoka ──────────────────────────────────────────────────────
// Guard dan session-nya terpisah dari akun toko. Semua halaman internal wajib
// melewati verifikasi dua langkah sebelum data lintas tenant dapat dibuka.
Route::prefix('platform')->name('platform.')->group(function () {
    Route::get('login', [PlatformAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [PlatformAuthController::class, 'login'])
        ->middleware('throttle:5,1')->name('login.store');

    Route::middleware('auth:platform')->group(function () {
        Route::get('two-factor', [PlatformAuthController::class, 'showTwoFactor'])->name('two-factor');
        Route::post('two-factor', [PlatformAuthController::class, 'verifyTwoFactor'])
            ->middleware('throttle:10,1')->name('two-factor.verify');
        Route::post('two-factor/resend', [PlatformAuthController::class, 'resendTwoFactor'])
            ->middleware('throttle:2,1')->name('two-factor.resend');
        Route::post('logout', [PlatformAuthController::class, 'logout'])->name('logout');

        Route::middleware('platform.2fa')->group(function () {
            Route::get('/', PlatformDashboardController::class)->name('dashboard');
            Route::get('businesses', [PlatformBusinessController::class, 'index'])->name('businesses.index');
            Route::get('businesses/{business}', [PlatformBusinessController::class, 'show'])->name('businesses.show');
            Route::patch('businesses/{business}/status', [PlatformBusinessController::class, 'updateStatus'])->name('businesses.status');
            Route::post('businesses/{business}/subscription', [PlatformBusinessController::class, 'updateSubscription'])->name('businesses.subscription');
            Route::post('businesses/{business}/trial', [PlatformBusinessController::class, 'extendTrial'])->name('businesses.trial');
            Route::post('businesses/{business}/impersonate', [ImpersonationController::class, 'start'])->name('impersonate.start');
            Route::post('impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
            Route::get('activity', PlatformActivityController::class)->name('activity');

            Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
                Route::get('/', [WithdrawalAdminController::class, 'index'])->name('index');
                Route::patch('{wd}/approve', [WithdrawalAdminController::class, 'approve'])->name('approve');
                Route::patch('{wd}/complete', [WithdrawalAdminController::class, 'complete'])->name('complete');
                Route::patch('{wd}/reject', [WithdrawalAdminController::class, 'reject'])->name('reject');
            });
        });
    });
});

// ── Kasir: masuk dengan PIN (tablet) ─────────────────────────────────────────
// Sengaja di luar grup 'business': perangkat belum punya sesi sampai PIN benar.
Route::get('/kasir', [PinLoginController::class, 'show'])->name('pin.show');
Route::post('/kasir/pair', [PinLoginController::class, 'pair'])->name('pin.pair')->middleware('throttle:20,1');
Route::post('/kasir/unpair', [PinLoginController::class, 'unpair'])->name('pin.unpair');
Route::post('/kasir/login', [PinLoginController::class, 'login'])->name('pin.login')->middleware('throttle:30,1');
Route::post('/kasir/logout', [PinLoginController::class, 'logout'])->name('pin.logout');

// ── Authenticated + Business ──────────────────────────────────────────────────
Route::middleware(['auth', 'business', 'owner.verified'])->group(function () {

    Route::post('/onboarding/tour/complete', [OnboardingController::class, 'completeTour'])->name('onboarding.tour.complete');
    Route::post('/onboarding/tour/restart', [OnboardingController::class, 'restartTour'])->name('onboarding.tour.restart');

    Route::get('/subscription/expired', [SubscriptionController::class, 'expired'])->name('subscription.expired');

    Route::prefix('saas')->name('saas.')->middleware('role:owner')->group(function () {
        Route::get('plans', [SubscriptionController::class, 'plans'])->name('plans');
        Route::get('subscription', [SubscriptionController::class, 'current'])->name('current');
        Route::post('subscription/{plan}', [SubscriptionController::class, 'subscribe'])->name('subscribe');
        Route::post('checkout', [MidtransController::class, 'createSubscriptionPayment'])
            ->middleware('throttle:10,1')
            ->name('checkout');
    });

});

Route::middleware(['auth', 'business', 'owner.verified', 'subscription'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('intelligence', [IntelligenceController::class, 'index'])
        ->middleware('role:owner,admin')->name('intelligence.index');
    Route::middleware(['role:owner,admin', 'json'])->prefix('intelligence')->name('intelligence.')->group(function () {
        Route::post('ask', [IntelligenceController::class, 'ask'])->middleware('throttle:30,1')->name('ask');
        Route::post('ask/stream', [IntelligenceController::class, 'askStream'])->middleware('throttle:30,1')->name('ask.stream');
        Route::get('history', [IntelligenceController::class, 'history'])->name('history');
        Route::post('insights/{insight}/read', [IntelligenceController::class, 'read'])->name('insights.read');
        Route::post('insights/{insight}/dismiss', [IntelligenceController::class, 'dismiss'])->name('insights.dismiss');
        Route::post('insights/{insight}/acknowledge', [IntelligenceController::class, 'acknowledge'])->name('insights.acknowledge');
        Route::post('insights/{insight}/snooze', [IntelligenceController::class, 'snooze'])->name('insights.snooze');
        Route::post('actions/{proposal}/approve', [IntelligenceController::class, 'approveAction'])->middleware('throttle:10,1')->name('actions.approve');
        Route::post('actions/{proposal}/execute', [IntelligenceController::class, 'executeAction'])->middleware('throttle:10,1')->name('actions.execute');
        Route::patch('agents/{agent}/toggle', [IntelligenceController::class, 'toggleAgent'])->middleware('throttle:20,1')->name('agents.toggle');
        Route::delete('conversations/{conversation}', [IntelligenceController::class, 'destroyConversation'])->name('conversations.destroy');
    });
    Route::post('intelligence/refresh', [IntelligenceController::class, 'refresh'])
        ->middleware(['role:owner,admin', 'throttle:6,1'])->name('intelligence.refresh');

    // Outlets
    Route::resource('outlets', OutletController::class);

    // Users
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::post('profile', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::post('profile/password-link', [UserController::class, 'sendPasswordLink'])->middleware('throttle:4,10')->name('profile.password.link');
    Route::post('profile/avatar', [\App\Http\Controllers\ProfileAvatarController::class, 'store'])->middleware('throttle:10,1')->name('profile.avatar.store');
    Route::delete('profile/avatar', [\App\Http\Controllers\ProfileAvatarController::class, 'destroy'])->middleware('throttle:10,1')->name('profile.avatar.destroy');
    Route::get('avatars/{user}', [\App\Http\Controllers\ProfileAvatarController::class, 'show'])->name('avatar.show');

    // Categories
    Route::patch('categories/{category}/kitchen', [ProductCategoryController::class, 'toggleKitchen'])
        ->name('categories.kitchen');
    Route::resource('categories', ProductCategoryController::class);

    // Products
    Route::resource('products', ProductController::class);
    Route::patch('products/{product}/toggle', [ProductController::class, 'toggle'])->name('products.toggle');

    // Product Bundles
    Route::resource('bundles', ProductBundleController::class)->except('show');
    Route::patch('bundles/{bundle}/toggle', [ProductBundleController::class, 'toggle'])->name('bundles.toggle');
    Route::post('products/{product}/variants', [ProductController::class, 'storeVariant'])->name('products.variants.store');
    Route::delete('products/{product}/variants/{variant}', [ProductController::class, 'destroyVariant'])->name('products.variants.destroy');
    Route::post('products/{product}/addons', [ProductController::class, 'storeAddon'])->name('products.addons.store');
    Route::delete('products/{product}/addons/{addon}', [ProductController::class, 'destroyAddon'])->name('products.addons.destroy');

    // Shifts
    Route::get('shifts', [CashierShiftController::class, 'index'])->name('shifts.index');
    Route::get('shifts/open', [CashierShiftController::class, 'open'])->name('shifts.open');
    Route::post('shifts/open', [CashierShiftController::class, 'store'])->name('shifts.store');
    Route::get('shifts/{shift}/close', [CashierShiftController::class, 'close'])->name('shifts.close');
    Route::post('shifts/{shift}/close', [CashierShiftController::class, 'closeStore'])->name('shifts.close.store');
    Route::get('shifts/{shift}', [CashierShiftController::class, 'show'])->name('shifts.show');

    // POS
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    // Dipanggil lewat fetch() dari layar POS — selalu balas JSON, termasuk saat
    // validasi gagal, supaya kasir melihat pesan asli bukan galat parsing.
    Route::middleware('json')->group(function () {
        Route::post('pos/order', [PosController::class, 'store'])->name('pos.store');
        Route::post('pos/qris/dynamic', [PosController::class, 'dynamicQris'])->name('pos.qris.dynamic');
        Route::post('pos/qris-draft', [PosController::class, 'createQrisDraft'])->name('pos.qris-draft');
        Route::post('pos/qris-confirm/{order}', [PosController::class, 'confirmQrisManual'])->name('pos.qris-confirm');
        Route::post('pos/hold', [PosController::class, 'hold'])->name('pos.hold');
        Route::get('pos/drafts', [PosController::class, 'drafts'])->name('pos.drafts');
        Route::post('pos/drafts/{order}/load', [PosController::class, 'loadDraft'])->name('pos.drafts.load');
        Route::delete('pos/drafts/{order}', [PosController::class, 'deleteDraft'])->name('pos.drafts.delete');
        Route::get('pos/customer/{customer}/points', [PosController::class, 'customerPoints'])->name('pos.customer.points');
    });

    // Orders
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order}/void', [OrderController::class, 'void'])->name('orders.void');
    Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');

    // Receipt
    Route::get('receipt/{order}', [ReceiptController::class, 'show'])->name('receipt.show');
    Route::get('receipt/{order}/print', [ReceiptController::class, 'print'])->name('receipt.print');
    Route::get('receipt/{order}/pdf', [ReceiptController::class, 'pdf'])->name('receipt.pdf');
    Route::get('receipt/{order}/kitchen', [ReceiptController::class, 'kitchen'])->name('receipt.kitchen');

    // Inventory
    Route::resource('inventory/ingredients', IngredientController::class)->names([
        'index' => 'ingredients.index',
        'create' => 'ingredients.create',
        'store' => 'ingredients.store',
        'show' => 'ingredients.show',
        'edit' => 'ingredients.edit',
        'update' => 'ingredients.update',
        'destroy' => 'ingredients.destroy',
    ]);
    Route::post('inventory/ingredients/{ingredient}/stock-in', [IngredientController::class, 'stockIn'])->name('ingredients.stock-in');
    Route::post('inventory/ingredients/{ingredient}/stock-out', [IngredientController::class, 'stockOut'])->name('ingredients.stock-out');
    Route::post('inventory/ingredients/{ingredient}/adjustment', [IngredientController::class, 'adjustment'])->name('ingredients.adjustment');
    Route::get('inventory/movements', [IngredientController::class, 'movements'])->name('inventory.movements');

    // Recipes
    Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
    Route::get('recipes/{product}', [RecipeController::class, 'edit'])->name('recipes.edit');
    Route::post('recipes/{product}', [RecipeController::class, 'update'])->name('recipes.update');

    // Kitchen
    Route::get('kitchen', [KitchenController::class, 'index'])->name('kitchen.index')
        ->middleware('role:owner,admin,kitchen');
    Route::patch('kitchen/orders/{order}/status', [KitchenController::class, 'updateStatus'])->name('kitchen.status');

    // Customers
    Route::resource('customers', CustomerController::class);

    // Promotions
    Route::resource('promotions', PromotionController::class);
    Route::patch('promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');

    // Reports
    // Financial reports are owner/admin only; the sidebar already hides them from other roles.
    Route::prefix('reports')->name('reports.')->middleware('role:owner,admin')->group(function () {
        Route::get('sales', [ReportController::class, 'sales'])->name('sales');
        // Sales Reporting v2 (flag: FEATURE_REPORTS_V2)
        Route::get('sales/analytics', [\App\Http\Controllers\SalesReportController::class, 'analytics'])->name('sales.analytics');
        Route::get('sales/breakdown', [\App\Http\Controllers\SalesReportController::class, 'breakdown'])->name('sales.breakdown');
        Route::get('sales/products', [\App\Http\Controllers\SalesReportController::class, 'products'])->name('sales.products');
        Route::get('sales/orders', [\App\Http\Controllers\SalesReportController::class, 'orders'])->name('sales.orders');
        Route::get('sales/orders/{orderId}', [\App\Http\Controllers\SalesReportController::class, 'order'])->whereNumber('orderId')->name('sales.order');
        Route::get('sales/summary', [\App\Http\Controllers\SalesReportController::class, 'summaryJson'])->name('sales.summary');
        Route::get('sales/trends', [\App\Http\Controllers\SalesReportController::class, 'trendsJson'])->name('sales.trends');
        Route::get('tax', [\App\Http\Controllers\FinanceReportController::class, 'tax'])->name('tax');
        Route::get('returns', [\App\Http\Controllers\FinanceReportController::class, 'returns'])->name('returns');
        Route::get('profit-loss', [\App\Http\Controllers\FinanceReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('sales/ask', [\App\Http\Controllers\SalesReportController::class, 'ask'])->name('sales.ask');
        Route::get('products', [ReportController::class, 'products'])->name('products');
        Route::get('cashier', [ReportController::class, 'cashier'])->name('cashier');
        Route::get('shift', [ReportController::class, 'shift'])->name('shift');
        Route::get('inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('profit', [ReportController::class, 'profit'])->name('profit');
        // Excel exports
        Route::get('sales/export', [ReportController::class, 'exportSales'])->name('sales.export');
        Route::get('products/export', [ReportController::class, 'exportProducts'])->name('products.export');
        Route::get('shift/export', [ReportController::class, 'exportShifts'])->name('shift.export');
        // PDF exports
        Route::get('sales/pdf', [ReportController::class, 'exportSalesPdf'])->name('sales.pdf');
        Route::get('products/pdf', [ReportController::class, 'exportProductsPdf'])->name('products.pdf');
        Route::get('profit/pdf', [ReportController::class, 'exportProfitPdf'])->name('profit.pdf');
    });

    // Finance: expenses, suppliers, purchases, payables, receivables (owner/admin only)
    Route::middleware('role:owner,admin')->group(function () {
        $c = \App\Http\Controllers\FinanceLedgerController::class;
        Route::get('expenses', [$c, 'expenses'])->name('expenses.index');
        Route::get('expenses/create', [$c, 'expenseCreate'])->name('expenses.create');
        Route::post('expenses', [$c, 'expenseStore'])->name('expenses.store');
        Route::post('expenses/{expense}/void', [$c, 'expenseVoid'])->name('expenses.void');
        Route::get('expense-categories', [$c, 'categories'])->name('expense-categories.index');
        Route::post('expense-categories', [$c, 'categoryStore'])->name('expense-categories.store');
        Route::post('expense-categories/{category}/toggle', [$c, 'categoryToggle'])->name('expense-categories.toggle');
        Route::get('suppliers', [$c, 'suppliers'])->name('suppliers.index');
        Route::post('suppliers', [$c, 'supplierStore'])->name('suppliers.store');
        Route::get('suppliers/{supplier}/edit', [$c, 'supplierEdit'])->name('suppliers.edit');
        Route::post('suppliers/{supplier}', [$c, 'supplierStore'])->name('suppliers.update');
        Route::get('purchases', [$c, 'purchases'])->name('purchases.index');
        Route::get('purchases/create', [$c, 'purchaseCreate'])->name('purchases.create');
        Route::post('purchases', [$c, 'purchaseStore'])->name('purchases.store');
        Route::get('purchases/{purchase}', [$c, 'purchaseShow'])->name('purchases.show');
        Route::post('purchases/{purchase}/pay', [$c, 'purchasePay'])->name('purchases.pay');
        Route::post('purchases/{purchase}/cancel', [$c, 'purchaseCancel'])->name('purchases.cancel');
        Route::get('payables', [$c, 'payables'])->name('payables.index');
        Route::get('receivables', [$c, 'receivables'])->name('receivables.index');
        Route::get('receivables/create', [$c, 'receivableCreate'])->name('receivables.create');
        Route::post('receivables', [$c, 'receivableStore'])->name('receivables.store');
        Route::get('receivables/{receivable}', [$c, 'receivableShow'])->name('receivables.show');
        Route::post('receivables/{receivable}/pay', [$c, 'receivablePay'])->name('receivables.pay');
        Route::post('receivables/{receivable}/void', [$c, 'receivableVoid'])->name('receivables.void');
    });

    // Audit Log
    if (config('pos.features.audit_log')) {
        Route::get('audit-log', [ActivityLogController::class, 'index'])->name('audit.index')
            ->middleware('role:owner,admin');
    }

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('business', [SettingController::class, 'business'])->name('business');
        Route::post('business', [SettingController::class, 'updateBusiness'])->name('business.update');
        Route::get('outlet', [SettingController::class, 'outlet'])->name('outlet');
        Route::post('outlet', [SettingController::class, 'updateOutlet'])->name('outlet.update');
        Route::get('receipt', [SettingController::class, 'receipt'])->name('receipt');
        Route::post('receipt', [SettingController::class, 'updateReceipt'])->name('receipt.update');
        Route::get('qris', [SettingController::class, 'qris'])->name('qris');
        Route::post('qris', [SettingController::class, 'updateQris'])->name('qris.update');
        Route::get('telegram', [SettingController::class, 'telegram'])->name('telegram');
        Route::post('telegram/link', [SettingController::class, 'telegramLink'])->name('telegram.link');
        Route::post('telegram/prefs', [SettingController::class, 'telegramPrefs'])->name('telegram.prefs');
        Route::post('telegram/test', [SettingController::class, 'telegramTest'])->name('telegram.test');
        Route::post('telegram/unlink', [SettingController::class, 'telegramUnlink'])->name('telegram.unlink');
    });

    // Midtrans — QRIS dynamic
    Route::prefix('midtrans')->name('midtrans.')->group(function () {
        Route::post('qris/create', [MidtransController::class, 'createQris'])->name('qris.create');
        Route::post('qris/status', [MidtransController::class, 'checkQrisStatus'])->name('qris.status');
    });

    // Barcode
    Route::prefix('barcodes')->name('barcodes.')->group(function () {
        Route::get('select', [BarcodeController::class, 'massSelect'])->name('select');
        Route::get('print', [BarcodeController::class, 'printLabels'])->name('print');
        Route::get('product/{product}', [BarcodeController::class, 'show'])->name('show');
    });

    // Merchant Balance & Withdrawal
    if (config('pos.features.balance')) {
        Route::prefix('balance')->name('balance.')->middleware('role:owner')->group(function () {
            Route::get('/', [BalanceController::class, 'index'])->name('index');
            Route::post('withdraw', [BalanceController::class, 'requestWithdrawal'])->name('withdraw');
            Route::delete('withdraw/{wd}', [BalanceController::class, 'cancelWithdrawal'])->name('cancel-withdrawal');
        });

    }
});

// Midtrans webhook — no auth, no CSRF (called by Midtrans server)
Route::post('/midtrans/callback', [MidtransController::class, 'callback'])
    ->name('midtrans.callback')
    ->withoutMiddleware(['web']);
