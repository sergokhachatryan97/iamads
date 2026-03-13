<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HeleketTestWebhookCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'services.heleket' => [
                'base' => 'https://api.heleket.com',
                'payment_key' => 'test-payment-key',
            ],
        ]);
    }

    public function test_artisan_command_sends_to_correct_endpoint_with_signature(): void
    {
        $capturedBody = null;
        $capturedSign = null;

        Http::fake([
            'https://api.heleket.com/v1/test-webhook/payment' => function ($request) use (&$capturedBody, &$capturedSign) {
                $capturedBody = $request->body();
                $capturedSign = $request->header('sign')[0] ?? null;
                return Http::response(['state' => 0, 'result' => []], 200);
            },
        ]);

        $this->artisan('heleket:test-webhook-payment', [
            'status' => 'paid',
            '--order_id' => 'test-order-123',
            '--currency' => 'USDT',
            '--network' => 'tron',
        ])->assertSuccessful();

        $this->assertNotEmpty($capturedBody);
        $body = json_decode($capturedBody, true);
        $this->assertSame('paid', $body['status']);
        $this->assertSame('test-order-123', $body['order_id']);
        $this->assertSame('USDT', $body['currency']);
        $this->assertSame('tron', $body['network']);
        $this->assertStringContainsString('/api/webhooks/payments/heleket', $body['url_callback']);

        $expectedSign = md5(base64_encode($capturedBody) . 'test-payment-key');
        $this->assertSame($expectedSign, $capturedSign);
    }

    public function test_artisan_command_uses_custom_url_callback(): void
    {
        $capturedBody = null;

        Http::fake([
            'https://api.heleket.com/v1/test-webhook/payment' => function ($request) use (&$capturedBody) {
                $capturedBody = json_decode($request->body(), true);
                return Http::response(['state' => 0, 'result' => []], 200);
            },
        ]);

        $this->artisan('heleket:test-webhook-payment', [
            'status' => 'check',
            '--order_id' => 'ord-1',
            '--url_callback' => 'https://ngrok.io/webhook',
        ])->assertSuccessful();

        $this->assertSame('https://ngrok.io/webhook', $capturedBody['url_callback']);
    }
}
