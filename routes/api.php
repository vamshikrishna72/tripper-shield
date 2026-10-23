<?php

use App\Http\Controllers\Api\MockInsurerCallbackController;
use App\Http\Controllers\Api\PolicyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PolicyFlow REST API Routes
|--------------------------------------------------------------------------
|
| Core REST API endpoints for TrippersShield PolicyFlow.
| Handles intake, status lookup, issuance lifecycle, provider callbacks,
| and timeout reconciliation.
|
*/

Route::prefix('policy-requests')->group(function () {
    Route::post('/', [PolicyRequestController::class, 'store'])->name('policy-requests.store');
    Route::get('/', [PolicyRequestController::class, 'index'])->name('policy-requests.index');
    Route::get('/{id}', [PolicyRequestController::class, 'show'])->name('policy-requests.show');
    Route::post('/{id}/submit', [PolicyRequestController::class, 'submit'])->name('policy-requests.submit');
    Route::post('/{id}/reconcile', [PolicyRequestController::class, 'reconcile'])->name('policy-requests.reconcile');
});

Route::post('/mock-insurer/callback', [MockInsurerCallbackController::class, 'handleCallback'])
    ->name('mock-insurer.callback');
