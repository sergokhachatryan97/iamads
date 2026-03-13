<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTO;

use App\Domain\Payments\PaymentStatus;

/**
 * Domain DTO: parsed and validated webhook event.
 */
readonly class GatewayWebhookEvent
{
    public function __construct(
        public string $orderId,
        public string $providerRef,
        public PaymentStatus $status,
        /** Raw webhook payload for audit */
        public array $raw,
    ) {}
}
