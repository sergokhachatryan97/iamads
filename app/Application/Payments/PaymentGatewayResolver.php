<?php

declare(strict_types=1);

namespace App\Application\Payments;

use App\Domain\Payments\PaymentGatewayInterface;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolver (Factory): resolves PaymentGateway by provider key.
 */
final class PaymentGatewayResolver
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    private array $gateways = [
        'heleket' => \App\Infrastructure\Payments\Heleket\HeleketGateway::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    public function resolve(string $provider): PaymentGatewayInterface
    {
        $class = $this->gateways[$provider] ?? null;
        if (!$class || !is_a($class, PaymentGatewayInterface::class, true)) {
            throw new InvalidArgumentException("Unknown payment provider: {$provider}");
        }
        return $this->container->make($class);
    }

    public function register(string $provider, string $gatewayClass): void
    {
        $this->gateways[$provider] = $gatewayClass;
    }

    public function supported(): array
    {
        return array_keys($this->gateways);
    }
}
