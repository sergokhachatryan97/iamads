<?php

use App\Http\Controllers\Api\Provider\TelegramAccountSyncController;
use App\Http\Controllers\Api\Provider\TelegramTaskClaimController;
use App\Http\Controllers\Api\Provider\TelegramTaskPullController;
use App\Http\Controllers\Api\Provider\TelegramTaskReportController;
use App\Http\Controllers\External\ExternalOrderController;
use Illuminate\Support\Facades\Route;

// External Orders API (X-Client-Token auth)
Route::prefix('external')->middleware('auth.external_client')->group(function () {
    Route::post('orders', [ExternalOrderController::class, 'store'])->name('external.orders.store');
    Route::get('orders', [ExternalOrderController::class, 'index'])->name('external.orders.index');
    Route::get('orders/{external_order_id}', [ExternalOrderController::class, 'show'])->name('external.orders.show');
});


Route::middleware(['auth.provider'])->prefix('provider/telegram')->group(function () {
    Route::post('/accounts/sync', [TelegramAccountSyncController::class, 'sync'])
        ->name('provider.telegram.accounts.sync');

    Route::post('/tasks/pull', [TelegramTaskPullController::class, 'pull'])
        ->name('provider.telegram.tasks.pull');

    Route::post('/tasks/claim', [TelegramTaskClaimController::class, 'claim'])
        ->name('provider.telegram.tasks.claim');

    Route::get('/getOrder', [TelegramTaskClaimController::class, 'claim'])
        ->name('provider.telegram.tasks.getOrder');


    Route::get('/check', [TelegramTaskReportController::class, 'check'])
        ->name('provider.telegram.tasks.check');

    Route::get('/ignore', [TelegramTaskReportController::class, 'ignore'])
        ->name('provider.telegram.tasks.ignore');

    Route::post('/tasks/report', [TelegramTaskReportController::class, 'report'])
        ->name('provider.telegram.tasks.report');
});
