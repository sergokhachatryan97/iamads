<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramTaskClaimService;
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
     * Claim tasks for the given performer phone.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function claim(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'account_identity' => ['required', 'string'],
        ]);

        $phone = $validated['account_identity'];

        $tasks = $this->claimService->claimForPhone($phone, 1);

        return response()->json([
            "id" =>$tasks[0]['task_id'] ?? null,
            "url" =>  $tasks[0]['link'] ?? null,
        ]);
    }
}
