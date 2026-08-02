<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefaultFlagController;
use App\Http\Controllers\LookupController;
use App\Http\Controllers\KhataController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\UdhaarController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'show'])->name('login');
    Route::post('login', [LoginController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');

    Route::get('register', [RegisterController::class, 'show'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');

    // Consent-gated credit check.
    Route::get('lookup', [LookupController::class, 'index'])->name('lookup.index');
    Route::post('lookup', [LookupController::class, 'search'])->name('lookup.search');
    Route::post('lookup/{customer}/consent', [LookupController::class, 'requestConsent'])
        ->middleware('throttle:15,1')
        ->name('lookup.consent.request');
    Route::post('lookup/{customer}/verify', [LookupController::class, 'verifyConsent'])
        ->middleware('throttle:20,1')
        ->name('lookup.consent.verify');
    Route::get('lookup/{customer}/report', [LookupController::class, 'report'])->name('lookup.report');

    Route::resource('customers', CustomerController::class)->except(['destroy']);

    // Udhar khata: the account view, one row per customer.
    Route::get('khata', [KhataController::class, 'index'])->name('khata.index');
    Route::get('khata/receive', [KhataController::class, 'receiveForm'])->name('khata.receive');
    Route::get('khata/{customer}/receive', [KhataController::class, 'receiveForm'])->name('khata.receive.customer');
    Route::post('khata/{customer}/receive', [KhataController::class, 'receive'])->name('khata.receive.store');
    Route::get('khata/{customer}', [KhataController::class, 'show'])->name('khata.show');

    // The individual credit entries that make up those accounts.
    Route::resource('udhaars', UdhaarController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('udhaars/{udhaar}/payments', [UdhaarController::class, 'recordPayment'])
        ->name('udhaars.payments.store');
    Route::post('udhaars/{udhaar}/write-off', [UdhaarController::class, 'writeOff'])
        ->name('udhaars.write-off');

    Route::post('customers/{customer}/flags', [DefaultFlagController::class, 'store'])->name('flags.store');

    Route::resource('staff', StaffController::class)->except(['show']);
});
