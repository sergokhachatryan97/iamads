<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Payments\HandleWebhookService;
use App\Application\Payments\PaymentGatewayResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private HandleWebhookService $handleWebhookService,
        private PaymentGatewayResolver $resolver,
    ) {}

    /**
     * POST /api/webhooks/payments/{provider}
     * Returns 200 OK on success; 400/403 on invalid signature or IP.
     */
    public function handle(string $provider, Request $request): Response
    {
        if (!in_array($provider, $this->resolver->supported(), true)) {
            return response('', 404);
        }

        $rawBody = $request->getContent();
        $headers = $request->headers->all();
        $ip = $request->ip() ?? '';

        try {
            $this->handleWebhookService->handle($provider, $rawBody, $headers, $ip);
            return response('', 200);
        } catch (\InvalidArgumentException $e) {
            Log::warning('Payment webhook validation failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return response('', 400);
        } catch (\Throwable $e) {
            Log::error('Payment webhook error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response('', 500);
        }
    }
}
