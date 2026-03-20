<?php

use App\Http\Controllers\Api\Client\BalanceTopupController;
use App\Http\Controllers\Api\FastOrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentMethodsController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\Provider\TelegramAccountSyncController;
use App\Http\Controllers\Api\Provider\TelegramTaskClaimController;
use App\Http\Controllers\Api\Provider\TelegramTaskPullController;
use App\Http\Controllers\Api\Provider\TelegramTaskReportController;
use App\Http\Controllers\Api\Provider\YouTubeAwaitingOrdersController;
use App\Http\Controllers\Api\Provider\YouTubeTaskClaimController;
use App\Http\Controllers\Api\Provider\YouTubeTaskReportController;
use App\Http\Controllers\External\ExternalOrderController;
use Illuminate\Support\Facades\Route;

// Fast orders (guest checkout; no auth)
Route::prefix('fast-orders')->group(function () {
    Route::post('/', [FastOrderController::class, 'store'])->name('fast-orders.store');
    Route::get('{uuid}', [FastOrderController::class, 'show'])->name('fast-orders.show');
    Route::post('{uuid}/payment-method', [FastOrderController::class, 'setPaymentMethod'])->name('fast-orders.payment-method');
    Route::post('{uuid}/simulate-payment-success', [FastOrderController::class, 'simulatePaymentSuccess'])->name('fast-orders.simulate-payment-success');
});

// Payment methods (public)
Route::get('payment-methods', [PaymentMethodsController::class, 'index'])
    ->name('payment-methods.index');

// Client balance top-up (auth:client required; client can only top up self)
Route::post('clients/{client}/balance/topup', [BalanceTopupController::class, 'topup'])
    ->middleware('auth:client')
    ->name('clients.balance.topup');

// Payment initiation
Route::post('payments/{provider}/initiate', [PaymentController::class, 'initiate'])
    ->name('payments.initiate');

// Payment webhooks (no auth; validated by signature + IP)
Route::post('webhooks/payments/{provider}', [PaymentWebhookController::class, 'handle'])
    ->name('webhooks.payments.handle');

// External Orders API (Bearer api_key auth – client account)
Route::prefix('external')->middleware('auth.api_client')->group(function () {
    Route::get('services', [\App\Http\Controllers\External\ExternalServiceController::class, 'index'])->name('external.services.index');
    Route::post('orders', [ExternalOrderController::class, 'store'])->name('external.orders.store');
    Route::get('orders', [ExternalOrderController::class, 'index'])->name('external.orders.index');
    Route::post('orders/statuses', [ExternalOrderController::class, 'statuses'])->name('external.orders.statuses');
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

// YouTube performer (same structure as Telegram: getOrder, check, ignore)
Route::middleware(['auth.provider'])->prefix('provider/youtube')->group(function () {
    Route::get('/orders-list', [YouTubeAwaitingOrdersController::class, 'index'])
        ->name('provider.youtube.awaiting-orders');
    Route::get('/getOrder', [YouTubeTaskClaimController::class, 'claim'])
        ->name('provider.youtube.tasks.getOrder');
    Route::get('/check', [YouTubeTaskReportController::class, 'check'])
        ->name('provider.youtube.tasks.check');
    Route::get('/ignore', [YouTubeTaskReportController::class, 'ignore'])
        ->name('provider.youtube.tasks.ignore');
});
