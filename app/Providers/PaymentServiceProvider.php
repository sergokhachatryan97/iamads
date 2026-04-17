<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Payments\Heleket\HeleketClient;
use App\Infrastructure\Payments\Heleket\HeleketGateway;
use App\Infrastructure\Payments\Heleket\HeleketTestWebhookClient;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HeleketClient::class, function () {
            $config = config('services.heleket', []);

            return new HeleketClient(
                baseUrl: $config['base'] ?? 'https://api.heleket.com',
                merchant: $config['merchant'] ?? '',
                paymentKey: $config['payment_key'] ?? '',
            );
        });

        $this->app->bind(HeleketGateway::class, function () {
            $config = config('services.heleket', []);

            return new HeleketGateway(
                client: $this->app->make(HeleketClient::class),
                webhookIp: $config['webhook_ip'] ?? '31.133.220.8',
                enforceWebhookIp: (bool) ($config['enforce_webhook_ip'] ?? true),
            );
        });

        $this->app->bind(HeleketTestWebhookClient::class, function () {
            $config = config('services.heleket', []);

            return new HeleketTestWebhookClient(
                baseUrl: $config['base'] ?? 'https://api.heleket.com',
                merchant: $config['merchant'] ?? '',
                paymentKey: $config['payment_key'] ?? '',
            );
        });

    }
}
