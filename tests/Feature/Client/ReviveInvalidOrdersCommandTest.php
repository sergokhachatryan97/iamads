<?php

namespace Tests\Feature\Client;

use App\Jobs\InspectTelegramLinkJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReviveInvalidOrdersCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        Schema::create('clients', function ($t) {
            $t->id();
            $t->decimal('balance', 20, 8)->default(0);
            $t->decimal('spent', 20, 8)->default(0);
            $t->timestamps();
        });
        Schema::create('categories', function ($t) {
            $t->id();
            $t->string('link_driver')->nullable();
            $t->timestamps();
        });
        Schema::create('services', function ($t) {
            $t->id();
            $t->unsignedBigInteger('category_id')->nullable();
            $t->string('mode')->nullable();
            $t->string('provider')->nullable();
            $t->string('provider_service_id')->nullable();
            $t->string('template_key')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('service_id')->nullable();
            $t->string('link')->nullable();
            $t->string('status')->nullable();
            $t->string('payment_source')->nullable();
            $t->decimal('charge', 20, 8)->default(0);
            $t->text('provider_payload')->nullable();
            $t->text('provider_last_error')->nullable();
            $t->timestamp('provider_last_error_at')->nullable();
            $t->timestamp('provider_sending_at')->nullable();
            $t->timestamps();
        });
        Schema::create('client_transactions', function ($t) {
            $t->id();
            $t->unsignedBigInteger('client_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->decimal('amount', 20, 8);
            $t->string('type');
            $t->timestamps();
        });

        DB::table('categories')->insert(['id' => 1, 'link_driver' => 'telegram']);
        DB::table('services')->insert(['id' => 2, 'category_id' => 1, 'mode' => 'manual']);
    }

    private function makeOrder(int $id, float $balance, array $payload = [], string $status = 'invalid_link'): void
    {
        DB::table('clients')->insert(['id' => $id, 'balance' => $balance, 'spent' => 0]);
        DB::table('orders')->insert([
            'id' => $id, 'client_id' => $id, 'service_id' => 2, 'link' => "https://t.me/+hash{$id}",
            'status' => $status, 'payment_source' => 'balance', 'charge' => 10,
            'provider_last_error' => 'paid messages',
            'provider_payload' => json_encode($payload + [
                'refund' => ['done' => true, 'amount' => 10, 'reason' => 'paid messages'],
                'telegram' => ['ok' => false, 'error_code' => 'PAID_MESSAGES'],
            ]),
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->makeOrder(1, 50);

        $this->artisan('orders:revive-invalid', ['ids' => [1]])->assertSuccessful();

        $this->assertSame('invalid_link', DB::table('orders')->where('id', 1)->value('status'));
        $this->assertEquals(50, DB::table('clients')->where('id', 1)->value('balance'));
        $this->assertSame(0, DB::table('client_transactions')->count());
        Queue::assertNothingPushed();
    }

    public function test_apply_recharges_and_reinspects(): void
    {
        $this->makeOrder(1, 50);

        $this->artisan('orders:revive-invalid', ['ids' => [1], '--apply' => true])->assertSuccessful();

        $order = DB::table('orders')->where('id', 1)->first();
        $payload = json_decode($order->provider_payload, true);
        $this->assertSame('validating', $order->status);
        $this->assertNull($order->provider_last_error);
        $this->assertArrayNotHasKey('refund', $payload);
        $this->assertArrayNotHasKey('telegram', $payload);
        $this->assertTrue($payload['refund_reverted']['done']);

        $client = DB::table('clients')->where('id', 1)->first();
        $this->assertEquals(40, $client->balance);
        $this->assertEquals(10, $client->spent);
        $this->assertEquals(-10, DB::table('client_transactions')->where('type', 'order_charge')->value('amount'));

        Queue::assertPushed(InspectTelegramLinkJob::class, fn ($job) => $job->orderId === 1);
    }

    public function test_skips_when_balance_too_low(): void
    {
        $this->makeOrder(1, 5);

        $this->artisan('orders:revive-invalid', ['ids' => [1], '--apply' => true])->assertSuccessful();

        $this->assertSame('invalid_link', DB::table('orders')->where('id', 1)->value('status'));
        $this->assertEquals(5, DB::table('clients')->where('id', 1)->value('balance'));
        Queue::assertNothingPushed();
    }

    public function test_skips_when_client_already_recreated_order(): void
    {
        $this->makeOrder(1, 50);
        DB::table('orders')->insert([
            'id' => 2, 'client_id' => 1, 'service_id' => 2, 'link' => 'https://t.me/+hash1',
            'status' => 'in_progress', 'payment_source' => 'balance', 'charge' => 10,
        ]);

        $this->artisan('orders:revive-invalid', ['ids' => [1], '--apply' => true])->assertSuccessful();

        $this->assertSame('invalid_link', DB::table('orders')->where('id', 1)->value('status'));
        Queue::assertNothingPushed();
    }

    public function test_skips_orders_not_invalid_link(): void
    {
        $this->makeOrder(1, 50, [], 'completed');

        $this->artisan('orders:revive-invalid', ['ids' => [1], '--apply' => true])->assertSuccessful();

        $this->assertSame('completed', DB::table('orders')->where('id', 1)->value('status'));
        $this->assertEquals(50, DB::table('clients')->where('id', 1)->value('balance'));
    }

    public function test_running_twice_does_not_double_charge(): void
    {
        $this->makeOrder(1, 50);

        $this->artisan('orders:revive-invalid', ['ids' => [1], '--apply' => true]);
        $this->artisan('orders:revive-invalid', ['ids' => [1], '--apply' => true]);

        $this->assertEquals(40, DB::table('clients')->where('id', 1)->value('balance'));
        $this->assertSame(1, DB::table('client_transactions')->count());
    }
}
