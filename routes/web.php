<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ReconciliationController;
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

    return Inertia::render('Dashboard', [
        'stats' => $service->dashboardStats($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null),
        'rows' => $service->reconciliationRows($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null),
        'dateRange' => $range,
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
    return Inertia::render('Account/Status', ['user' => $request->user()]);
})->middleware('auth')->name('account.status');

Route::middleware(['auth', 'account.active'])->group(function (): void {
    Route::get('/imports/upload', fn () => Inertia::render('Imports/Upload'))->name('imports.upload');
    Route::post('/imports/upload', [UploadReportsController::class, 'store'])->name('imports.upload.store');
    Route::get('/finance/reconciliation', [ReconciliationController::class, 'index'])->name('finance.reconciliation');
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
