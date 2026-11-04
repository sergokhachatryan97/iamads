<?php

namespace Tests\Feature\Provider;

use App\Http\Controllers\Api\Provider\TelegramTaskClaimController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelegramClaimScopeCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('services', function ($t) {
            $t->id();
            $t->string('template_key')->nullable();
            $t->softDeletes();
        });
        DB::table('services')->insert([
            ['id' => 1, 'template_key' => 'channel_subscribe'],
            ['id' => 2, 'template_key' => 'premium_templates'],
            ['id' => 3, 'template_key' => 'channel_subscribe'],
        ]);
    }

    private function resolveScope(int $serviceId): string
    {
        $controller = app(TelegramTaskClaimController::class);
        $m = new \ReflectionMethod($controller, 'resolveScope');

        return $m->invoke($controller, $serviceId);
    }

    public function test_cache_hit_skips_database(): void
    {
        Redis::shouldReceive('get')->once()->with('tg:svc_scope:1')->andReturn('premium');
        Redis::shouldReceive('setex')->never();
        DB::enableQueryLog();

        $this->assertSame('premium', $this->resolveScope(1));
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_cache_miss_reads_db_and_stores_scope(): void
    {
        $expectedPremium = \App\Support\TelegramPremiumTemplateScope::isPremiumTemplateKey('premium_templates')
            ? 'premium' : 'default';

        Redis::shouldReceive('get')->with('tg:svc_scope:2')->andReturn(null);
        Redis::shouldReceive('setex')->once()->with('tg:svc_scope:2', 300, $expectedPremium);

        $this->assertSame($expectedPremium, $this->resolveScope(2));
    }

    public function test_redis_down_falls_back_to_db(): void
    {
        Redis::shouldReceive('get')->andThrow(new \RuntimeException('redis down'));
        Redis::shouldReceive('setex')->andThrow(new \RuntimeException('redis down'));

        // service 3: resolveScope keeps a per-process static cache, so don't reuse ids across tests
        $this->assertSame('default', $this->resolveScope(3));
    }
}
