<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTO;

/**
 * Domain DTO: vendor-agnostic payment intent for initiation.
 */
readonly class PaymentIntent
{
    public function __construct(
        public string $orderId,
        public string $amount,
        public string $currency,
        public string $provider,
        /** Success redirect URL (e.g. balance/add?status=success&provider=X&order_id=Y) */
        public string $urlSuccess,
        /** Return/back URL (e.g. balance/add?status=return&provider=X&order_id=Y) */
        public string $urlReturn,
        /** Webhook URL (absolute, from APP_URL) */
        public string $webhookUrl,
    ) {}
}
