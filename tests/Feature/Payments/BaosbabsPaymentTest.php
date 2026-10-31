<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\BalanceLedgerEntry;
use App\Models\Client;
use App\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BaosbabsPaymentTest extends TestCase
{
    private const STATUS_URL = 'https://crypto.baosbabs.org/api/payment/*/status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePaymentsTablesExist();
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'services.baosbabs' => [
                'base' => 'https://crypto.baosbabs.org',
                'api_key' => 'pk_test',
                'webhook_ip' => '',
                'assets' => [
                    'baosbabs_trx' => 'TRX',
                    'baosbabs_usdt_trc20' => 'USDT',
                    'baosbabs_usdt_bep20' => 'USDT_BEP20',
                ],
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

    public function test_initiate_creates_invoice_with_asset_and_signed_pay_page(): void
    {
        Http::fake([
            'https://crypto.baosbabs.org/api/payment/create' => Http::response([
                'success' => true,
                'data' => [
                    'transaction_id' => 'order-b1',
                    'wallet_address' => 'TXYZwallet1234567890123456789012345',
                    'asset' => 'USDT',
                    'amount_usd' => '10.50',
                    'amount_crypto' => '10.504312',
                    'expires_at' => now()->addMinutes(30)->toIso8601String(),
                    'status' => 'pending',
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/baosbabs_usdt_trc20/initiate', [
            'order_id' => 'order-b1',
            'amount' => '10.50',
            'currency' => 'USD',
        ]);

        $response->assertStatus(200)->assertJson([
            'provider_ref' => 'order-b1',
            'status' => 'pending',
        ]);
        $this->assertStringContainsString('/pay/crypto/order-b1?', $response->json('pay_url'));
        $this->assertStringContainsString('signature=', $response->json('pay_url'));

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://crypto.baosbabs.org/api/payment/create'
                && $request->hasHeader('X-API-Key', 'pk_test')
                && $request['transaction_id'] === 'order-b1'
                && $request['asset'] === 'USDT'
                && $request['amount_usd'] === '10.50'
                && $request['postback_url'] === 'http://127.0.0.1:8000/api/webhooks/payments/baosbabs_usdt_trc20';
        });

        $payment = Payment::where('order_id', 'order-b1')->first();
        $this->assertSame('baosbabs_usdt_trc20', $payment->provider);
        $this->assertSame('TXYZwallet1234567890123456789012345', $payment->meta['invoice']['wallet_address']);

        $this->get($response->json('pay_url'))
            ->assertOk()
            ->assertSee('TXYZwallet1234567890123456789012345')
            ->assertSee('10.504312');
    }

    public function test_initiate_uses_bep20_asset_for_bep20_provider(): void
    {
        Http::fake([
            'https://crypto.baosbabs.org/api/payment/create' => Http::response([
                'success' => true,
                'data' => ['transaction_id' => 'order-b2', 'wallet_address' => '0xabc', 'amount_crypto' => '5.00'],
            ], 200),
        ]);

        $this->postJson('/api/payments/baosbabs_usdt_bep20/initiate', [
            'order_id' => 'order-b2',
            'amount' => '5',
            'currency' => 'USD',
        ])->assertStatus(200);

        Http::assertSent(fn (Request $request) => $request['asset'] === 'USDT_BEP20');
    }

    public function test_initiate_api_error_returns_422(): void
    {
        Http::fake([
            'https://crypto.baosbabs.org/api/payment/create' => Http::response([
                'error' => 'INSUFFICIENT_WALLETS',
                'message' => 'No available wallets',
            ], 400),
        ]);

        $this->postJson('/api/payments/baosbabs_trx/initiate', [
            'order_id' => 'order-b3',
            'amount' => '5',
            'currency' => 'USD',
        ])->assertStatus(422);

        $this->assertNull(Payment::where('order_id', 'order-b3')->first());
    }

    public function test_verified_completed_postback_credits_balance_once(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'baosbabs-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'balance' => 0,
        ]);
        $payment = $this->pendingPayment('order-paid', $client->id, '25.00');

        Http::fake([self::STATUS_URL => $this->statusResponse('order-paid', 'completed')]);

        $postback = ['transaction_id' => 'order-paid', 'status' => 'completed', 'amount_usd' => '25.00'];
        $this->postJson('/api/webhooks/payments/baosbabs_usdt_trc20', $postback)->assertStatus(200);
        $this->postJson('/api/webhooks/payments/baosbabs_usdt_trc20', $postback)->assertStatus(200);

        $payment->refresh();
        $client->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertEqualsWithDelta(25.0, (float) $client->balance, 0.01);
        $this->assertSame(1, BalanceLedgerEntry::where('payment_id', $payment->id)->where('type', 'credit')->count());
    }

    public function test_forged_postback_is_ignored_when_api_says_pending(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'baosbabs-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'balance' => 0,
        ]);
        $payment = $this->pendingPayment('order-forged', $client->id, '50.00');

        Http::fake([self::STATUS_URL => $this->statusResponse('order-forged', 'pending')]);

        $this->postJson('/api/webhooks/payments/baosbabs_usdt_trc20', [
            'transaction_id' => 'order-forged',
            'status' => 'completed',
        ])->assertStatus(200);

        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertEqualsWithDelta(0.0, (float) $client->refresh()->balance, 0.01);
    }

    public function test_postback_for_unknown_transaction_returns_400(): void
    {
        Http::fake([self::STATUS_URL => Http::response([
            'error' => 'PAYMENT_NOT_FOUND',
            'message' => 'Payment not found',
        ], 404)]);

        $this->postJson('/api/webhooks/payments/baosbabs_trx', [
            'transaction_id' => 'nope',
            'status' => 'completed',
        ])->assertStatus(400);
    }

    public function test_expired_status_maps_to_expired(): void
    {
        $payment = $this->pendingPayment('order-exp', null, '5.00');

        Http::fake([self::STATUS_URL => $this->statusResponse('order-exp', 'expired')]);

        $this->postJson('/api/webhooks/payments/baosbabs_usdt_trc20', ['transaction_id' => 'order-exp'])
            ->assertStatus(200);

        $this->assertSame('expired', $payment->refresh()->status);
    }

    public function test_pay_page_requires_signature_and_status_reports_paid_redirect(): void
    {
        $payment = $this->pendingPayment('order-page', null, '5.00');
        $payment->update([
            'status' => 'paid',
            'meta' => ['invoice' => ['wallet_address' => 'Taddr'], 'url_success' => 'http://127.0.0.1:8000/done'],
        ]);

        $this->get('/pay/crypto/order-page')->assertForbidden();

        $this->getJson(URL::signedRoute('payments.crypto.status', ['orderId' => 'order-page']))
            ->assertOk()
            ->assertJson(['status' => 'paid', 'redirect_url' => 'http://127.0.0.1:8000/done']);

        $this->get(URL::signedRoute('payments.crypto.show', ['orderId' => 'order-page']))
            ->assertRedirect('http://127.0.0.1:8000/done');
    }

    private function pendingPayment(string $orderId, ?int $clientId, string $amount): Payment
    {
        return Payment::create([
            'client_id' => $clientId,
            'order_id' => $orderId,
            'provider' => 'baosbabs_usdt_trc20',
            'provider_ref' => $orderId,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => 'pending',
        ]);
    }

    private function statusResponse(string $transactionId, string $status)
    {
        return Http::response([
            'success' => true,
            'data' => [
                'transaction_id' => $transactionId,
                'status' => $status,
                'asset' => 'USDT',
                'amount_usd' => '25.00',
                'amount_crypto' => '25.01',
                'amount_received' => '25.01',
            ],
        ], 200);
    }
}
