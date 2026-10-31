<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Models\Client;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $schema = DB::getSchemaBuilder();
        if (! $schema->hasTable('clients')) {
            $schema->create('clients', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->decimal('balance', 15, 8)->default(0);
                $table->decimal('spent', 15, 8)->default(0);
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }
        if (! $schema->hasTable('payments')) {
            $schema->create('payments', function ($table) {
                $table->id();
                $table->foreignId('client_id')->nullable();
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

    public function test_shows_only_own_payments_with_stats_and_filters(): void
    {
        $client = $this->makeClient();
        $other = $this->makeClient();

        $this->payment($client, 'own-paid', 'paid', '25.00', ['paid_at' => now()]);
        $this->payment($client, 'own-pending', 'pending', '7.00', ['pay_url' => 'https://pay.example/continue']);
        $this->payment($client, 'own-expired', 'expired', '3.00');
        $this->payment($other, 'other-paid', 'paid', '999.00', ['paid_at' => now()]);

        $this->actingAs($client, 'client')
            ->get(route('client.payments.index'))
            ->assertOk()
            ->assertSee('+$25.00')
            ->assertSee('$7.00')
            ->assertSee('https://pay.example/continue')
            ->assertSee('$3.00')
            ->assertDontSee('999.00');

        $this->actingAs($client, 'client')
            ->get(route('client.payments.index', ['status' => 'paid']))
            ->assertOk()
            ->assertSee('+$25.00')
            ->assertDontSee('$7.00');
    }

    public function test_old_pending_payment_is_not_offered_for_continue(): void
    {
        $client = $this->makeClient();
        $payment = $this->payment($client, 'old-pending', 'pending', '4.00', ['pay_url' => 'https://pay.example/stale']);
        $payment->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->actingAs($client, 'client')
            ->get(route('client.payments.index'))
            ->assertOk()
            ->assertDontSee('https://pay.example/stale')
            ->assertSee('Not completed');
    }

    public function test_empty_state_and_guest_redirect(): void
    {
        $this->get(route('client.payments.index'))->assertRedirect();

        $this->actingAs($this->makeClient(), 'client')
            ->get(route('client.payments.index'))
            ->assertOk()
            ->assertSee('You have no payments yet.');
    }

    public function test_payment_partials_render_for_dashboard_block(): void
    {
        $client = $this->makeClient();
        $paid = $this->payment($client, 'dash-paid', 'paid', '12.00', ['paid_at' => now()]);
        $open = $this->payment($client, 'dash-open', 'pending', '6.00', ['pay_url' => 'https://pay.example/dash']);

        $html = view('client.payments._method', ['payment' => $paid, 'small' => true])->render()
            .view('client.payments._status', ['payment' => $paid, 'continueSince' => now()->subHour()])->render()
            .view('client.payments._status', ['payment' => $open, 'continueSince' => now()->subHour()])->render();

        $this->assertStringContainsString('ph-ico--usdt', $html);
        $this->assertStringContainsString('TRC-20', $html);
        $this->assertStringContainsString('Paid', $html);
        $this->assertStringContainsString('https://pay.example/dash', $html);
    }

    private function makeClient(): Client
    {
        return Client::create([
            'name' => 'Client',
            'email' => 'ph-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'balance' => 0,
        ]);
    }

    private function payment(Client $client, string $orderId, string $status, string $amount, array $extra = []): Payment
    {
        return Payment::create(array_merge([
            'client_id' => $client->id,
            'order_id' => $orderId,
            'provider' => 'baosbabs_usdt_trc20',
            'provider_ref' => $orderId,
            'amount' => $amount,
            'currency' => 'USD',
            'status' => $status,
        ], $extra));
    }
}
