<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskClaimService;
use App\Support\TelegramPremiumTemplateScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account-driven claim: performer sends phone, core returns tasks for that phone.
 * Endpoint: POST /api/provider/telegram/tasks/claim
 */
class TelegramTaskClaimController extends Controller
{
    public function __construct(
        private TelegramTaskClaimService $claimService
    ) {}

    /**
     * Claim tasks for the given performer phone (non-premium Telegram templates only).
     */
    public function claim(Request $request): JsonResponse
    {
        return $this->claimByScope($request, TelegramPremiumTemplateScope::SCOPE_DEFAULT);
    }

    /**
     * Claim tasks for the given performer phone (premium Telegram templates only).
     */
    public function claimPremium(Request $request): JsonResponse
    {
        return $this->claimByScope($request, TelegramPremiumTemplateScope::SCOPE_PREMIUM);
    }

    private function claimByScope(Request $request, string $scope): JsonResponse
    {
        $validated = $request->validate([
            'account_identity' => ['required', 'string'],
            'service_id' => ['required', 'integer'],
        ]);

        $phone = $validated['account_identity'];
        $serviceId = (int) $validated['service_id'];

        $tasks = $this->claimService->claimForPhone($phone, 1, $scope, $serviceId);

        if (empty($tasks)) {
            return response()->json(['ok' => true, 'count' => 0, 'tasks' => []]);
        }

        $task = $tasks[0];
        $action = $task['action'] ?? 'subscribe';

        $response = [
            'id' => $task['task_id'],
            'url' => $task['link'],
            'action' => $action,
        ];

        return response()->json($response);
    }
}
