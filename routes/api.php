<?php

use App\Http\Controllers\Api\ProviderWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/provider', [ProviderWebhookController::class, 'handle']);
