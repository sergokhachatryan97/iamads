<?php

use App\Http\Controllers\Api\Client\BalanceTopupController;
use App\Http\Controllers\Api\Provider\AppAwaitingOrdersController;
use App\Http\Controllers\Api\Provider\AppTaskClaimController;
use App\Http\Controllers\Api\Provider\AppTaskReportController;
use App\Http\Controllers\Api\ProviderApiController;
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

// Provider-style API (Socpanel / Perfect Panel compatible)
// POST /api/v2 with JSON body: key, action, and action-specific fields
Route::post('v2', [ProviderApiController::class, 'handle'])
    ->middleware('auth.provider_api_key')
    ->name('provider-api.v2');

// External Orders API (Bearer api_key auth – client account)
Route::prefix('external')->middleware('auth.api_client')->group(function () {
    Route::get('services', [\App\Http\Controllers\External\ExternalServiceController::class, 'index'])->name('external.services.index');
    Route::post('orders', [ExternalOrderController::class, 'store'])->name('external.orders.store');
    Route::get('orders', [ExternalOrderController::class, 'index'])->name('external.orders.index');
    Route::post('orders/statuses', [ExternalOrderController::class, 'statuses'])->name('external.orders.statuses');
    Route::get('orders/{external_order_id}', [ExternalOrderController::class, 'show'])->name('external.orders.show');
});


Route::middleware(['auth.provider'])->prefix('provider/telegram')->group(function () {
    Route::get('/getOrder', [TelegramTaskClaimController::class, 'claim'])
        ->name('provider.telegram.tasks.getOrder');

    Route::get('/check', [TelegramTaskReportController::class, 'check'])
        ->name('provider.telegram.tasks.check');

    Route::get('/ignore', [TelegramTaskReportController::class, 'ignore'])
        ->name('provider.telegram.tasks.ignore');

    Route::get('/premium/getOrder', [TelegramTaskClaimController::class, 'claimPremium'])
        ->name('provider.telegram.premium.tasks.getOrder');

    Route::get('/premium/check', [TelegramTaskReportController::class, 'checkPremium'])
        ->name('provider.telegram.premium.tasks.check');

    Route::get('/premium/ignore', [TelegramTaskReportController::class, 'ignorePremium'])
        ->name('provider.telegram.premium.tasks.ignore');
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

// App performer (App Store / Google Play: getOrder, check, ignore)
Route::middleware(['auth.provider'])->prefix('provider/app')->group(function () {
    Route::get('/orders-list', [AppAwaitingOrdersController::class, 'index'])
        ->name('provider.app.awaiting-orders');
    Route::get('/getOrder', [AppTaskClaimController::class, 'claim'])
        ->name('provider.app.tasks.getOrder');
    Route::get('/check', [AppTaskReportController::class, 'check'])
        ->name('provider.app.tasks.check');
    Route::get('/ignore', [AppTaskReportController::class, 'ignore'])
        ->name('provider.app.tasks.ignore');
});
