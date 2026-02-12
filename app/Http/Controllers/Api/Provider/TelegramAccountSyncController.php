<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\TelegramAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller for provider account synchronization.
 * 
 * Endpoint: POST /api/provider/telegram/accounts/sync
 * Provider sends a list of Telegram accounts to upsert.
 */
class TelegramAccountSyncController extends Controller
{
    /**
     * Sync accounts from provider.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'accounts' => 'required|array',
            'accounts.*.provider_account_id' => 'required|string',
            'accounts.*.phone' => 'required|string',
            'accounts.*.is_active' => 'boolean',
            'accounts.*.meta' => 'array',
        ]);

        $accounts = $validated['accounts'];
        $synced = 0;
        $errors = [];

        foreach ($accounts as $accountData) {
            try {
                TelegramAccount::updateOrCreate(
                    [
                        'provider_account_id' => $accountData['provider_account_id'],
                    ],
                    [
                        'phone' => $accountData['phone'],
                        'is_active' => $accountData['is_active'] ?? true,
                        'meta' => $accountData['meta'] ?? [],
                    ]
                );
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'provider_account_id' => $accountData['provider_account_id'] ?? null,
                    'error' => $e->getMessage(),
                ];
                Log::error('Failed to sync account', [
                    'provider_account_id' => $accountData['provider_account_id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'synced' => $synced,
            'errors' => $errors,
        ]);
    }
}
