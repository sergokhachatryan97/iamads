<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Max\MaxTaskClaimService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaxTaskClaimController extends Controller
{
    public function __construct(
        private MaxTaskClaimService $claimService
    ) {}

    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_identity' => ['required', 'string'],
            'service_id' => ['required', 'integer'],
        ]);

        $serviceId = (int) $validated['service_id'];

        $result = $this->claimService->claim($validated['account_identity'], $serviceId);

        if ($result === null) {
            return response()->json(['ok' => true, 'count' => 0, 'tasks' => []]);
        }

        return response()->json([
            'id' => $result['task_id'],
            'url' => $result['link'],
            'action' => $result['action'],
            'comment_text' => $result['comment_text'] ?? null,
        ]);
    }
}
