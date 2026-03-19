<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\YouTube\YouTubeTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * YouTube performer report: check (mark done) and ignore (mark failed).
 * Uses task_id (YouTubeTask ULID) for check/ignore.
 */
class YouTubeTaskReportController extends Controller
{
    public function __construct(
        private YouTubeTaskService $taskService
    ) {}

    /**
     * Check: mark task as completed successfully. GET /check?task_id=...
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'string'],
        ]);

        $result = $this->taskService->reportTaskResult($validated['task_id'], [
            'state' => 'done',
            'ok' => true,
            'error' => null,
        ]);

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Failed to check task',
            ], 400);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Ignore: mark task as failed/skipped. GET /ignore?task_id=...
     */
    public function ignore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'string'],
        ]);

        $result = $this->taskService->reportTaskResult($validated['task_id'], [
            'state' => 'failed',
            'ok' => false,
            'error' => null,
            'ignored' => true,
        ]);

        if (!($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Failed to ignore task',
            ], 400);
        }

        return response()->json(['ok' => true]);
    }
}
