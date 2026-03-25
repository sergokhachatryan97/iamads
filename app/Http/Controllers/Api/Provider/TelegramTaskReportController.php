<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskService;
use App\Support\TelegramPremiumTemplateScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for provider task reporting.
 *
 * Endpoint: POST /api/provider/telegram/tasks/report
 * Provider reports result by task_id.
 */
class TelegramTaskReportController extends Controller
{
    public function __construct(
        private TelegramTaskService $taskService
    ) {}

    /**
     * Report task result.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => 'required|string',
            'state' => 'required|string|in:done,pending,failed',
            'ok' => 'boolean',
            'error' => 'string|nullable',
            'retry_after' => 'integer|nullable',
            'provider_task_id' => 'string|nullable',
            'data' => 'array|nullable',
        ]);

        $result = $this->taskService->reportTaskResult(
            $validated['task_id'],
            [
                'state' => $validated['state'],
                'ok' => $validated['ok'] ?? false,
                'error' => $validated['error'] ?? null,
                'retry_after' => $validated['retry_after'] ?? null,
                'provider_task_id' => $validated['provider_task_id'] ?? null,
                'data' => $validated['data'] ?? null,
            ]
        );

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Failed to report task',
            ], 400);
        }

        return response()->json([
            'ok' => true,
        ]);
    }


    public function check(Request $request): JsonResponse
    {
        return $this->checkByScope($request, TelegramPremiumTemplateScope::SCOPE_DEFAULT);
    }

    public function checkPremium(Request $request): JsonResponse
    {
        return $this->checkByScope($request, TelegramPremiumTemplateScope::SCOPE_PREMIUM);
    }

    public function ignore(Request $request): JsonResponse
    {
        return $this->ignoreByScope($request, TelegramPremiumTemplateScope::SCOPE_DEFAULT);
    }

    public function ignorePremium(Request $request): JsonResponse
    {
        return $this->ignoreByScope($request, TelegramPremiumTemplateScope::SCOPE_PREMIUM);
    }

    private function checkByScope(Request $request, string $scope): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
            'account_identity' => 'required|string',
        ]);

        $result = $this->taskService->reportTaskResult(
            $validated['order_id'],
            [
                'state' => 'done',
                'ok' => true,
                'error' => null,
                'retry_after' => null,
                'provider_task_id' => null,
                'data' => null,
            ],
            $scope
        );

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Failed to report task',
            ], 400);
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    private function ignoreByScope(Request $request, string $scope): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
            'account_identity' => 'required|string',
        ]);

        $result = $this->taskService->reportTaskResult(
            $validated['order_id'],
            [
                'state' => 'failed',
                'ok' => false,
                'error' => null,
                'retry_after' => null,
                'provider_task_id' => null,
                'data' => null,
            ],
            $scope
        );

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Failed to report task',
            ], 400);
        }

        return response()->json([
            'ok' => true,
        ]);
    }
}
