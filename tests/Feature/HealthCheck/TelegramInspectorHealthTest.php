<?php

namespace Tests\Feature\HealthCheck;

use App\Services\HealthCheck\ServerHealthCheckService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramInspectorHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.telegram_inspector.url' => 'http://inspector.test']);
    }

    private function check(): array
    {
        $service = app(ServerHealthCheckService::class);
        $m = new \ReflectionMethod($service, 'checkTelegramInspector');

        return $m->invoke($service);
    }

    public function test_ok_when_bot_and_accounts_ready(): void
    {
        Http::fake(['inspector.test/health' => Http::response(['status' => 'ok', 'bot_ready' => true, 'user_accounts' => 5, 'user_available' => 5])]);

        $r = $this->check();
        $this->assertSame('ok', $r['status']);
        $this->assertStringContainsString('5/5', $r['message']);
    }

    public function test_ok_when_no_user_sessions_loaded(): void
    {
        Http::fake(['inspector.test/health' => Http::response(['bot_ready' => true, 'user_accounts' => 0, 'user_available' => 0])]);

        $this->assertSame('ok', $this->check()['status']);
    }

    public function test_bad_when_unreachable(): void
    {
        Http::fake(['inspector.test/health' => Http::response('', 502)]);

        $r = $this->check();
        $this->assertSame('bad', $r['status']);
        $this->assertStringContainsString('DOWN', $r['message']);
    }

    public function test_bad_when_bot_not_ready(): void
    {
        Http::fake(['inspector.test/health' => Http::response(['bot_ready' => false, 'user_accounts' => 5, 'user_available' => 5])]);

        $r = $this->check();
        $this->assertSame('bad', $r['status']);
        $this->assertStringContainsString('bot not connected', $r['message']);
    }

    public function test_bad_when_all_accounts_unavailable_after_resample(): void
    {
        Http::fake(['inspector.test/health' => Http::response(['bot_ready' => true, 'user_accounts' => 5, 'user_available' => 0])]);

        $r = $this->check();
        $this->assertSame('bad', $r['status']);
        $this->assertStringContainsString('no user accounts available', $r['message']);
        Http::assertSentCount(2);
    }

    public function test_transient_zero_available_does_not_alert(): void
    {
        Http::fakeSequence('inspector.test/health')
            ->push(['bot_ready' => true, 'user_accounts' => 5, 'user_available' => 0])
            ->push(['bot_ready' => true, 'user_accounts' => 5, 'user_available' => 3]);

        $this->assertSame('ok', $this->check()['status']);
    }
}
