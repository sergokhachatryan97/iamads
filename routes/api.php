<?php

use App\Http\Controllers\Api\Provider\TelegramAccountSyncController;
use App\Http\Controllers\Api\Provider\TelegramTaskPullController;
use App\Http\Controllers\Api\Provider\TelegramTaskReportController;
use App\Http\Controllers\ProviderWebhookController;
use Illuminate\Support\Facades\Route;

// Provider webhook endpoints (no auth required, uses HMAC signature)

Route::post('/provider/webhook/account-action', [ProviderWebhookController::class, 'accountAction'])
    ->name('provider.webhook.account-action');

// Provider pull architecture endpoints (authenticated via X-Provider-Token header)
Route::middleware(['auth.provider'])->prefix('provider/telegram')->group(function () {
    Route::post('/accounts/sync', [TelegramAccountSyncController::class, 'sync'])
        ->name('provider.telegram.accounts.sync');

    Route::post('/tasks/pull', [TelegramTaskPullController::class, 'pull'])
        ->name('provider.telegram.tasks.pull');

    Route::post('/tasks/report', [TelegramTaskReportController::class, 'report'])
        ->name('provider.telegram.tasks.report');
});
