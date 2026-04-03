<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\BalanceLedgerEntry;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HeleketPaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePaymentsTablesExist();
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'services.heleket' => [
                'base' => 'https://api.heleket.com',
                'merchant' => 'test-merchant-uuid',
                'payment_key' => 'test-payment-key',
                'webhook_ip' => '31.133.220.8',
                'enforce_webhook_ip' => false,
            ],
        ]);
    }

    /** Ensure payments/ledger tables exist (for in-memory DB when migrations may not run) */
    private function ensurePaymentsTablesExist(): void
    {
        $schema = DB::getSchemaBuilder();
        if (! $schema->hasTable('clients')) {
            $schema->create('clients', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->decimal('balance', 15, 8)->default(0);
                $table->decimal('spent', 15, 8)->default(0);
                $table->string('password');
                $table->timestamps();
            });
        }
        if (! $schema->hasTable('payments')) {
            $schema->create('payments', function ($table) {
                $table->id();
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
                $table->string('order_id')->unique();
                $table->string('provider');
                $table->string('provider_ref')->nullable();
                $table->string('amount');
                $table->string('currency');
                $table->string('status');
                $table->text('pay_url')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
        if (! $schema->hasTable('payment_events')) {
            $schema->create('payment_events', function ($table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
                $table->string('provider');
                $table->string('provider_ref')->nullable();
                $table->string('event_hash')->unique();
                $table->string('status');
                $table->json('payload');
                $table->timestamps();
            });
        }
        if (! $schema->hasTable('balance_ledger_entries')) {
            $schema->create('balance_ledger_entries', function ($table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->decimal('amount_decimal', 14, 2);
                $table->string('currency', 8)->default('USD');
                $table->string('type', 16);
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['payment_id', 'type'], 'balance_ledger_payment_type_unique');
            });
        }
        if (! $schema->hasTable('client_transactions')) {
            $schema->create('client_transactions', function ($table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->unsignedBigInteger('order_id')->nullable();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->decimal('amount', 12, 2);
                $table->string('type');
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_initiate_creates_payment_with_provider_ref_and_pay_url(): void
    {
        Http::fake([
            'https://api.heleket.com/*' => Http::response([
                'state' => 0,
                'result' => [
                    'uuid' => 'heleket-invoice-uuid-123',
                    'url' => 'https://pay.heleket.com/xxx',
                    'payment_status' => 'check',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/heleket/initiate', [
            'order_id' => 'order-123',
            'amount' => '10.50',
            'currency' => 'USD',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'provider_ref' => 'heleket-invoice-uuid-123',
                'pay_url' => 'https://pay.heleket.com/xxx',
                'status' => 'pending',
            ]);

        $payment = Payment::where('order_id', 'order-123')->first();
        $this->assertNotNull($payment);
        $this->assertSame('heleket-invoice-uuid-123', $payment->provider_ref);
        $this->assertSame('https://pay.heleket.com/xxx', $payment->pay_url);
    }

    public function test_webhook_idempotency_same_raw_body_twice_creates_one_event(): void
    {
        $payment = Payment::create([
            'order_id' => 'order-456',
            'provider' => 'heleket',
            'provider_ref' => 'inv-789',
            'amount' => '5.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $bodyWithoutSign = [
            'amount' => 5.0,
            'order_id' => 'order-456',
            'payment_status' => 'paid',
            'uuid' => 'inv-789',
        ];
        ksort($bodyWithoutSign);
        $body = $bodyWithoutSign;
        $body['sign'] = md5(base64_encode(json_encode($bodyWithoutSign)).'test-payment-key');

        $this->withServerVariables(['REMOTE_ADDR' => '31.133.220.8']);

        $response1 = $this->postJson('/api/webhooks/payments/heleket', $body, [
            'Content-Type' => 'application/json',
        ]);
        $response1->assertStatus(200);

        $response2 = $this->postJson('/api/webhooks/payments/heleket', $body, [
            'Content-Type' => 'application/json',
        ]);
        $response2->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame(1, $payment->events()->count());
    }

    public function test_webhook_paid_idempotency_ledger_credited_only_once(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'test-ledger-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'balance' => 0,
        ]);

        $payment = Payment::create([
            'client_id' => $client->id,
            'order_id' => 'order-ledger-idemp',
            'provider' => 'heleket',
            'provider_ref' => 'inv-ledger',
            'amount' => '25.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $bodyWithoutSign = [
            'amount' => 25.0,
            'order_id' => 'order-ledger-idemp',
            'payment_status' => 'paid',
            'uuid' => 'inv-ledger',
        ];
        ksort($bodyWithoutSign);
        $body = $bodyWithoutSign;
        $body['sign'] = md5(base64_encode(json_encode($bodyWithoutSign)).'test-payment-key');

        $this->postJson('/api/webhooks/payments/heleket', $body)->assertStatus(200);
        $this->postJson('/api/webhooks/payments/heleket', $body)->assertStatus(200);

        $client->refresh();
        $this->assertEqualsWithDelta(25.0, (float) $client->balance, 0.01);

        $ledgerCount = BalanceLedgerEntry::where('payment_id', $payment->id)->where('type', 'credit')->count();
        $this->assertSame(1, $ledgerCount);
    }

    public function test_webhook_invalid_signature_returns_400(): void
    {
        Payment::create([
            'order_id' => 'order-999',
            'provider' => 'heleket',
            'provider_ref' => 'inv-999',
            'amount' => '1.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $body = [
            'order_id' => 'order-999',
            'uuid' => 'inv-999',
            'payment_status' => 'paid',
            'sign' => 'invalid-signature',
        ];
        $rawBody = json_encode($body);

        $this->withServerVariables(['REMOTE_ADDR' => '31.133.220.8']);

        $response = $this->postJson('/api/webhooks/payments/heleket', $body, [
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(400);
    }

    public function test_webhook_status_mapping_paid_over_to_paid(): void
    {
        $payment = Payment::create([
            'order_id' => 'order-paid-over',
            'provider' => 'heleket',
            'provider_ref' => 'inv-paid-over',
            'amount' => '10.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $body = [
            'order_id' => 'order-paid-over',
            'uuid' => 'inv-paid-over',
            'payment_status' => 'paid_over',
        ];
        ksort($body);
        $body['sign'] = md5(base64_encode(json_encode($body)).'test-payment-key');
        // Re-add sign for the request (it was already in body when we ksort'd - actually ksort mutates, sign is there)
        // After ksort the body has order_id, payment_status, uuid, sign - we compute sign from body without sign
        $bodyWithoutSign = ['order_id' => $body['order_id'], 'payment_status' => $body['payment_status'], 'uuid' => $body['uuid']];
        ksort($bodyWithoutSign);
        $body['sign'] = md5(base64_encode(json_encode($bodyWithoutSign)).'test-payment-key');

        $this->withServerVariables(['REMOTE_ADDR' => '31.133.220.8']);

        $this->postJson('/api/webhooks/payments/heleket', $body, [
            'Content-Type' => 'application/json',
        ])->assertStatus(200);

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
    }

    public function test_webhook_status_mapping_wrong_amount_to_pending(): void
    {
        $payment = Payment::create([
            'order_id' => 'order-wrong',
            'provider' => 'heleket',
            'provider_ref' => 'inv-wrong',
            'amount' => '10.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $bodyWithoutSign = [
            'order_id' => 'order-wrong',
            'uuid' => 'inv-wrong',
            'payment_status' => 'wrong_amount',
        ];
        ksort($bodyWithoutSign);
        $body = $bodyWithoutSign;
        $body['sign'] = md5(base64_encode(json_encode($bodyWithoutSign)).'test-payment-key');

        $this->withServerVariables(['REMOTE_ADDR' => '31.133.220.8']);

        $this->postJson('/api/webhooks/payments/heleket', $body, [
            'Content-Type' => 'application/json',
        ])->assertStatus(200);

        $payment->refresh();
        // wrong_amount => PENDING, transition PENDING->PENDING is invalid. So we need to allow same-status?
        // Actually the state machine: PENDING can go to PAID, FAILED, EXPIRED. PENDING->PENDING is not allowed.
        // So we'd get DomainException and we catch and return (ignore). So status stays pending. Good.
        $this->assertSame('pending', $payment->status);
    }

    public function test_webhook_status_mapping_cancel_to_expired(): void
    {
        $payment = Payment::create([
            'order_id' => 'order-cancel',
            'provider' => 'heleket',
            'provider_ref' => 'inv-cancel',
            'amount' => '5.00',
            'currency' => 'USD',
            'status' => 'pending',
        ]);

        $bodyWithoutSign = [
            'order_id' => 'order-cancel',
            'uuid' => 'inv-cancel',
            'payment_status' => 'cancel',
        ];
        ksort($bodyWithoutSign);
        $body = $bodyWithoutSign;
        $body['sign'] = md5(base64_encode(json_encode($bodyWithoutSign)).'test-payment-key');

        $this->withServerVariables(['REMOTE_ADDR' => '31.133.220.8']);

        $this->postJson('/api/webhooks/payments/heleket', $body, [
            'Content-Type' => 'application/json',
        ])->assertStatus(200);

        $payment->refresh();
        $this->assertSame('expired', $payment->status);
    }
}
