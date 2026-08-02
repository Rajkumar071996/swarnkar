<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\KhataController;
use App\Http\Controllers\Api\V1\UdhaarController;
use Illuminate\Support\Facades\Route;

/*
| The contract the Flutter client will build against. Everything mirrors the
| web flows, including the consent gate on score retrieval.
*/

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.auth.login');

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

        Route::post('lookup/search', [LookupController::class, 'search'])->name('api.lookup.search');
        Route::post('lookup/{customer}/consent', [LookupController::class, 'requestConsent'])
            ->middleware('throttle:15,1')
            ->name('api.lookup.consent');
        Route::post('lookup/{customer}/verify', [LookupController::class, 'verifyConsent'])
            ->middleware('throttle:20,1')
            ->name('api.lookup.verify');
        Route::get('lookup/{customer}/score', [LookupController::class, 'score'])->name('api.lookup.score');

        Route::apiResource('customers', CustomerController::class)->only(['index', 'store', 'show'])
            ->names('api.customers');

        Route::apiResource('udhaars', UdhaarController::class)->only(['index', 'store', 'show'])
            ->names('api.udhaars');
        Route::post('udhaars/{udhaar}/payments', [UdhaarController::class, 'recordPayment'])
            ->name('api.udhaars.payments');

        Route::get('khata', [KhataController::class, 'index'])->name('api.khata.index');
        Route::get('khata/{customer}', [KhataController::class, 'show'])->name('api.khata.show');
        Route::get('customers/{customer}/exposure', [KhataController::class, 'exposure'])
            ->name('api.customers.exposure');
    });
});
