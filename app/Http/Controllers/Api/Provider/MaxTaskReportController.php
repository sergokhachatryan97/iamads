<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Max\MaxTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaxTaskReportController extends Controller
{
    public function __construct(
        private MaxTaskService $taskService
    ) {}

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'account_identity' => ['required', 'string'],
        ]);

        $result = $this->taskService->reportTaskResult(
            $validated['order_id'],
            ['state' => 'done', 'ok' => true]
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Failed'], 400);
        }

        return response()->json(['ok' => true]);
    }

    public function ignore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string'],
            'account_identity' => ['required', 'string'],
            'error' => ['string', 'nullable'],
        ]);

        $result = $this->taskService->markIgnored($validated['order_id'], $validated['error'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'error' => $result['error'] ?? 'Failed'], 400);
        }

        return response()->json(['ok' => true]);
    }
}
