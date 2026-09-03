<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadReportsController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Dashboard');
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
});
