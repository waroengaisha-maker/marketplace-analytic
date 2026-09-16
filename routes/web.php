<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\HppController;
use App\Http\Controllers\HppMappingController;
use App\Http\Controllers\IncomeReconciliationController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ShopeeApiController;
use App\Http\Controllers\UploadReportsController;
use App\Services\MarketplaceReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (Request $request, MarketplaceReconciliationService $service) {
    if ($request->user()?->isAdmin()) {
        return redirect()->route('admin.users.index');
    }

    $validated = $request->validate([
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date', 'after_or_equal:from'],
    ]);
    $range = $service->orderDateRange($request->user()->id);
    $hasAppliedFilter = $request->hasAny(['from', 'to']);

    return Inertia::render('Dashboard', [
        'stats' => $hasAppliedFilter
            ? $service->dashboardStats($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null)
            : [],
        'rows' => $hasAppliedFilter
            ? $service->reconciliationRows($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null)
            : [],
        'dateRange' => $range,
        'hasAppliedFilter' => $hasAppliedFilter,
        'filters' => [
            'from' => $validated['from'] ?? $range['min'],
            'to' => $validated['to'] ?? $range['max'],
        ],
    ]);
})->middleware(['auth', 'account.active']);

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->middleware('guest')->name('login');

Route::get('/register', function () {
    return Inertia::render('Auth/Register');
})->middleware('guest')->name('register');

Route::get('/account/status', function (Request $request) {
    return Inertia::render('Account/Status', ['user' => $request->user()->toSafeArray()]);
})->middleware('auth')->name('account.status');

Route::get('/account/subscription', function (Request $request) {
    return Inertia::render('Account/Subscription', ['user' => $request->user()->toSafeArray()]);
})->middleware('auth')->name('account.subscription');

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::get('/imports/upload', fn () => Inertia::render('Imports/Upload'))->name('imports.upload');
    Route::post('/imports/upload', [UploadReportsController::class, 'store'])->name('imports.upload.store');
    Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::get('/finance/reconciliation', [ReconciliationController::class, 'index'])->name('finance.reconciliation');
    Route::get('/finance/income-reconciliation', [IncomeReconciliationController::class, 'index'])->name('finance.income-reconciliation');
    Route::get('/products/hpp', [HppController::class, 'index'])->name('products.hpp');
    Route::get('/products/hpp-mapping', [HppMappingController::class, 'index'])->name('products.hpp-mapping');
    Route::post('/products/hpp-mapping', [HppMappingController::class, 'store'])->name('products.hpp-mapping.store');
    Route::post('/products/hpp-mapping/sync-template-catalog', [HppMappingController::class, 'syncTemplateCatalog'])->name('products.hpp-mapping.sync-template-catalog');

    Route::post('/products/hpp-mapping/reallocate', [HppMappingController::class, 'reallocate'])->name('products.hpp-mapping.reallocate');
    Route::get('/integrations/shopee-api', [ShopeeApiController::class, 'index'])->name('integrations.shopee-api');
    Route::get('/integrations/shopee-api/status', [ShopeeApiController::class, 'status'])->name('integrations.shopee-api.status');
    Route::get('/integrations/shopee-api/test', [ShopeeApiController::class, 'testConnection'])->name('integrations.shopee-api.test');
    Route::get('/integrations/shopee-api/orders', [ShopeeApiController::class, 'orders'])->name('integrations.shopee-api.orders');
    Route::get('/integrations/shopee-api/orders/{order_sn}', [ShopeeApiController::class, 'orderDetail'])->name('integrations.shopee-api.order-detail')->where('order_sn', '[A-Za-z0-9_.-]+');
    Route::get('/integrations/shopee-api/income', [ShopeeApiController::class, 'income'])->name('integrations.shopee-api.income');
    Route::post('/integrations/shopee-api/clear', [ShopeeApiController::class, 'clear'])->name('integrations.shopee-api.clear');
    Route::post('/integrations/shopee-api/configure', [ShopeeApiController::class, 'configure'])->name('integrations.shopee-api.configure');
    Route::get('/integrations/shopee-api/authorize', [ShopeeApiController::class, 'authorize'])->name('integrations.shopee-api.authorize');
    Route::get('/integrations/shopee-api/shopee-auth', [ShopeeApiController::class, 'shopeeCallback'])->name('integrations.shopee-api.shopee-auth');
    Route::post('/integrations/shopee-api/sync-orders', [ShopeeApiController::class, 'syncOrders'])->name('integrations.shopee-api.sync-orders');
    Route::post('/integrations/shopee-api/sync-income', [ShopeeApiController::class, 'syncIncome'])->name('integrations.shopee-api.sync-income');
    Route::post('/integrations/shopee-api/sync-escrow', [ShopeeApiController::class, 'syncEscrow'])->name('integrations.shopee-api.sync-escrow');
    Route::get('/integrations/shopee-api/validate', [ShopeeApiController::class, 'validate'])->name('integrations.shopee-api.validate');
    Route::post('/integrations/shopee-api/promote', [ShopeeApiController::class, 'promote'])->name('integrations.shopee-api.promote');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/roles', [UserController::class, 'index'])->name('roles.index');
    Route::get('/admins', [UserController::class, 'index'])->name('admins.index');
    Route::get('/users', [UserController::class, 'access'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    Route::post('/users/{user}/trial', [UserController::class, 'trial'])->name('users.trial');
    Route::post('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role');
});
