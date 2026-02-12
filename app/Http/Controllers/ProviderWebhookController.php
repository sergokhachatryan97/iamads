<?php

namespace App\Http\Controllers;

use App\Models\TelegramAccount;
use App\Services\Telegram\AccountOnboardingCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderWebhookController extends Controller
{

    public function accountAction(Request $request): JsonResponse
    {
        $signature = $request->header('X-Provider-Signature');
        $secret = config('services.provider.webhook_secret');

        if (!$secret) return response()->json(['error' => 'Webhook not configured'], 500);
        if (!$signature) return response()->json(['error' => 'Missing signature'], 401);

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $raw, $secret);

        if (!hash_equals($expected, $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();
        $accountId = (int) ($payload['account_id'] ?? 0);
        $action = (string) ($payload['action'] ?? '');
        $state = strtolower((string)($payload['state'] ?? 'done'));

        if (!$accountId || !$action) return response()->json(['error' => 'Missing account_id or action'], 400);
        if (!in_array($state, ['done', 'failed'], true)) return response()->json(['error' => 'Invalid state'], 400);

        $account = TelegramAccount::find($accountId);
        if (!$account) return response()->json(['error' => 'Account not found'], 404);

        // Independent actions (set_profile_name, set_visibility) are handled by job completion
        // Only call completion service for onboarding chain actions
        $independentActions = ['set_profile_name', 'set_visibility'];
        if (!in_array($action, $independentActions, true)) {
            app(AccountOnboardingCompletionService::class)->handleCompletion(
                $accountId,
                $action,
                $state,
                (bool) ($payload['ok'] ?? true),
                $payload['error'] ?? null,
                $payload['task_id'] ?? null
            );
        }

        return response()->json(['status' => 'processed'], 200);
    }
}
