<?php

declare(strict_types=1);

namespace App\Domain\Payments;

use App\Domain\Payments\DTO\GatewayWebhookEvent;
use App\Domain\Payments\DTO\PaymentInitiationResult;
use App\Domain\Payments\DTO\PaymentIntent;

/**
 * Payment gateway contract. Implementations handle vendor-specific API and webhook parsing.
 */
interface PaymentGatewayInterface
{
    public function initiate(PaymentIntent $intent): PaymentInitiationResult;

    /**
     * Parse and validate webhook. Must validate IP allowlist and signature internally.
     * Throws on invalid request.
     */
    public function parseWebhook(string $rawBody, array $headers, string $ip): GatewayWebhookEvent;
}
