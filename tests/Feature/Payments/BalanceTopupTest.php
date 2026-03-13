<?php

declare(strict_types=1);

namespace Tests\Feature\Payments;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BalanceTopupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensurePaymentsTablesExist();
        config([
            'app.url' => 'http://127.0.0.1:8000',
            'payments.enabled_providers' => ['heleket'],
            'payments.methods' => [
                'heleket' => [
                    'code' => 'heleket',
                    'title' => 'Cryptocurrency (Heleket)',
                    'notes' => 'Pay with crypto via Heleket gateway.',
                ],
            ],
            'services.heleket' => [
                'base' => 'https://api.heleket.com',
                'merchant' => 'test-merchant',
                'payment_key' => 'test-key',
                'webhook_ip' => '31.133.220.8',
                'enforce_webhook_ip' => false,
            ],
        ]);
    }

    private function ensurePaymentsTablesExist(): void
    {
        $schema = DB::getSchemaBuilder();
        if (!$schema->hasTable('clients')) {
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
        if (!$schema->hasTable('payments')) {
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
    }

    public function test_topup_with_provider_heleket_works(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'topup-test@example.com',
            'password' => bcrypt('password'),
            'balance' => 0,
        ]);

        Http::fake([
            'https://api.heleket.com/*' => Http::response([
                'state' => 0,
                'result' => [
                    'uuid' => 'topup-uuid-1',
                    'url' => 'https://pay.heleket.com/topup-xxx',
                    'payment_status' => 'check',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($client, 'client')
            ->postJson("/api/clients/{$client->id}/balance/topup", [
                'amount' => 50.00,
                'currency' => 'USD',
                'provider' => 'heleket',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'provider_ref' => 'topup-uuid-1',
                'pay_url' => 'https://pay.heleket.com/topup-xxx',
                'status' => 'pending',
            ]);

        $payment = \App\Models\Payment::where('client_id', $client->id)->first();
        $this->assertNotNull($payment);
        $this->assertStringStartsWith('balance_' . $client->id . '_', $payment->order_id);
        $this->assertSame('50', $payment->amount);
    }

    public function test_topup_with_invalid_provider_returns_422(): void
    {
        $client = Client::create([
            'name' => 'Test Client',
            'email' => 'topup-invalid@example.com',
            'password' => bcrypt('password'),
            'balance' => 0,
        ]);

        $response = $this->actingAs($client, 'client')
            ->postJson("/api/clients/{$client->id}/balance/topup", [
                'amount' => 10,
                'currency' => 'USD',
                'provider' => 'stripe',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    public function test_payment_methods_returns_heleket(): void
    {
        $response = $this->getJson('/api/payment-methods');

        $response->assertStatus(200)
            ->assertJson([
                'methods' => [
                    [
                        'code' => 'heleket',
                        'title' => 'Cryptocurrency (Heleket)',
                        'notes' => 'Pay with crypto via Heleket gateway.',
                    ],
                ],
            ]);
    }
}
