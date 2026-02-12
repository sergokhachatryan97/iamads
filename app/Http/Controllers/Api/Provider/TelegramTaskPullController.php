<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for provider task pulling.
 *
 * Endpoint: POST /api/provider/telegram/tasks/pull
 * Provider pulls tasks (assignments) in batches.
 */
class TelegramTaskPullController extends Controller
{
    public function __construct(
        private TelegramTaskService $taskService
    ) {}

    /**
     * Pull tasks for provider.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pull(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'integer|min:1|max:1000',
        ]);

        $limit = (int) ($validated['limit'] ?? 1000);

        // Generate tasks if needed (background generation)
        $this->taskService->generateTasks($limit);

        // Lease tasks
        $tasks = $this->taskService->leaseTasks($limit);

        return response()->json([
            'ok' => true,
            'tasks' => $tasks,
            'count' => count($tasks),
        ]);
    }
}
