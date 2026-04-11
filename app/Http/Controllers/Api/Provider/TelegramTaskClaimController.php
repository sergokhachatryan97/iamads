<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskClaimService;
use App\Support\TelegramPremiumTemplateScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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

        // Track claim attempt for stats (HyperLogLog, 1h TTL)
        $hllKey = "tg:claim_attempts:{$serviceId}:" . now()->format('Y-m-d-H');
        Redis::pfadd($hllKey, [$phone]);
        Redis::expire($hllKey, 7200);

        try {
            $tasks = $this->claimService->claimForPhone($phone, 1, $scope, $serviceId);

            if (empty($tasks)) {
                return response()->json(['ok' => true, 'count' => 0, 'tasks' => []]);
            }

            $task = $tasks[0];
            $action = $task['action'] ?? 'subscribe';

            return response()->json([
                'id' => $task['task_id'],
                'url' => $task['link'],
                'action' => $action,
            ]);
        } finally {
            // Release the MySQL connection back to the pool immediately so it
            // doesn't sit idle (Sleep state) waiting for the next request on
            // the same PHP-FPM worker. Critical mitigation for max_user_connections.
            DB::disconnect();
        }
    }
}
