<?php

declare(strict_types=1);

namespace App\Domain\Payments\DTO;

/**
 * Domain DTO: result of gateway initiate call.
 */
readonly class PaymentInitiationResult
{
    public function __construct(
        public string $provider,
        public string $providerRef,
        public string $payUrl,
        /** Raw gateway response for persistence (meta) */
        public array $raw,
    ) {}
}
