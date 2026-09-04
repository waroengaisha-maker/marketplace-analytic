<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\UploadReportsController;
use App\Http\Controllers\ReconciliationController;
use App\Services\MarketplaceReconciliationService;
use Inertia\Inertia;

Route::get('/', function (Request $request, MarketplaceReconciliationService $service) {
    $validated = $request->validate([
        'from' => ['nullable', 'date'],
        'to' => ['nullable', 'date', 'after_or_equal:from'],
    ]);
    $range = $service->orderDateRange($request->user()->id);

    return Inertia::render('Dashboard', [
        'stats' => $service->dashboardStats($request->user()->id, $validated['from'] ?? null, $validated['to'] ?? null),
        'dateRange' => $range,
        'filters' => [
            'from' => $validated['from'] ?? $range['min'],
            'to' => $validated['to'] ?? $range['max'],
        ],
    ]);
})->middleware('auth');

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->middleware('guest')->name('login');

Route::get('/register', function () {
    return Inertia::render('Auth/Register');
})->middleware('guest')->name('register');

Route::middleware('auth')->group(function (): void {
    Route::get('/imports/upload', fn () => Inertia::render('Imports/Upload'))->name('imports.upload');
    Route::post('/imports/upload', [UploadReportsController::class, 'store'])->name('imports.upload.store');
    Route::get('/finance/reconciliation', [ReconciliationController::class, 'index'])->name('finance.reconciliation');
});
