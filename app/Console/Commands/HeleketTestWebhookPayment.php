<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Payments\Heleket\HeleketTestWebhookClient;
use Illuminate\Console\Command;

class HeleketTestWebhookPayment extends Command
{
    protected $signature = 'heleket:test-webhook-payment
                            {status=paid : Webhook status: process, check, paid, paid_over, fail, wrong_amount, cancel, system_fail, refund_process, refund_fail, refund_paid, locked}
                            {--order_id= : Order ID for the webhook}
                            {--uuid= : Provider UUID (if both uuid and order_id provided, identified by uuid)}
                            {--currency=USDT : Currency code}
                            {--network=tron : Network code}
                            {--url_callback= : Webhook callback URL (default: APP_URL/api/webhooks/payments/heleket)}';

    protected $description = 'Send a test payment webhook to Heleket API';

    private const VALID_STATUSES = [
        'process', 'check', 'paid', 'paid_over', 'fail', 'wrong_amount',
        'cancel', 'system_fail', 'refund_process', 'refund_fail', 'refund_paid', 'locked',
    ];

    public function handle(HeleketTestWebhookClient $client): int
    {
        $status = $this->argument('status');
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $this->error("Invalid status. Must be one of: " . implode(', ', self::VALID_STATUSES));
            return self::FAILURE;
        }

        $orderId = $this->option('order_id');
        $uuid = $this->option('uuid');
        if (!$orderId && !$uuid) {
            $this->error('At least one of --order_id or --uuid is required');
            return self::FAILURE;
        }

        $urlCallback = $this->option('url_callback');
        if ($urlCallback === '' || $urlCallback === null) {
            $baseUrl = rtrim(config('app.url'), '/');
            $urlCallback = "{$baseUrl}/api/webhooks/payments/heleket";
        }

        $payload = [
            'url_callback' => $urlCallback,
            'currency' => $this->option('currency'),
            'network' => $this->option('network'),
            'status' => $status,
        ];
        if ($orderId !== null && $orderId !== '') {
            $payload['order_id'] = $orderId;
        }
        if ($uuid !== null && $uuid !== '') {
            $payload['uuid'] = $uuid;
        }

        try {
            $result = $client->sendTestPaymentWebhook($payload);
            $this->info('Test webhook sent successfully.');
            $this->line('Response: ' . json_encode($result, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
