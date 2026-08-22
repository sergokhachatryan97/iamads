<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Payments\Heleket\HeleketClient;
use App\Infrastructure\Payments\Heleket\HeleketGateway;
use App\Infrastructure\Payments\Heleket\HeleketTestWebhookClient;
use App\Infrastructure\Payments\Cryptomus\CryptomusClient;
use App\Infrastructure\Payments\Cryptomus\CryptomusGateway;
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

        $this->app->bind(CryptomusClient::class, function () {
            $config = config('services.cryptomus', []);

            return new CryptomusClient(
                baseUrl: $config['base'] ?? 'https://api.cryptomus.com',
                merchant: $config['merchant'] ?? '',
                paymentKey: $config['payment_key'] ?? '',
            );
        });

        $this->app->bind(CryptomusGateway::class, function () {
            $config = config('services.cryptomus', []);

            return new CryptomusGateway(
                client: $this->app->make(CryptomusClient::class),
                webhookIp: $config['webhook_ip'] ?? '91.227.144.54',
                enforceWebhookIp: (bool) ($config['enforce_webhook_ip'] ?? true),
            );
        });
    }
}
