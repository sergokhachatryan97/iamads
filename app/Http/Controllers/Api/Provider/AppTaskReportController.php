<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\App\AppTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * App performer report: check (mark done) and ignore (mark failed).
 */
class AppTaskReportController extends Controller
{
    public function __construct(
        private AppTaskService $taskService
    ) {}

    /**
     * Check: mark task as completed successfully. GET /check?task_id=...
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'string'],
        ]);

        try {
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
        } finally {
            DB::disconnect();
        }
    }

    /**
     * Ignore: mark task as failed/skipped. GET /ignore?task_id=...
     */
    public function ignore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_id' => ['required', 'string'],
        ]);

        try {
            $result = $this->taskService->markIgnored($validated['task_id']);

            if (!($result['ok'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'error' => $result['error'] ?? 'Failed to ignore task',
                ], 400);
            }

            return response()->json(['ok' => true]);
        } finally {
            DB::disconnect();
        }
    }
}
