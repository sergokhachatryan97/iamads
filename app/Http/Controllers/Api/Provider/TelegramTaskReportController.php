<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskService;
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
}
