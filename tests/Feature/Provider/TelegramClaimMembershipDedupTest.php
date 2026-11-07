<?php

namespace Tests\Feature\Provider;

use App\Models\TelegramAccountLinkState;
use App\Models\TelegramTask;
use App\Services\Telegram\TelegramTaskClaimService;
use App\Support\TelegramPremiumTemplateScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TelegramClaimMembershipDedupTest extends TestCase
{
    private const LINK = 'https://t.me/+testInviteHash';
    private const PHONE = '+10000000001';
    private const TASK_ID = '01JTESTTASK0000000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('orders', function ($t) {
            $t->id();
            $t->unsignedBigInteger('service_id')->nullable();
            $t->string('link')->nullable();
            $t->string('mode')->nullable();
            $t->string('status')->nullable();
            $t->string('execution_phase')->nullable();
            $t->integer('remains')->default(0);
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('telegram_tasks', function ($t) {
            $t->string('id', 26)->primary();
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('service_id')->nullable();
            $t->string('action')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('leased_until')->nullable();
            $t->text('payload')->nullable();
            $t->text('result')->nullable();
            $t->timestamps();
        });
        Schema::create('telegram_order_memberships', function ($t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('service_id')->nullable();
            $t->string('account_phone', 32);
            $t->string('link_hash', 64);
            $t->string('link')->nullable();
            $t->string('state', 20);
            $t->string('last_error')->nullable();
            $t->timestamps();
            $t->unique(['order_id', 'account_phone', 'link_hash'], 'tg_ord_phone_link_uq');
        });
        Schema::create('telegram_account_link_states', function ($t) {
            $t->id();
            $t->string('account_phone', 32);
            $t->string('link_hash', 64);
            $t->string('action');
            $t->string('state', 20);
            $t->string('last_error')->nullable();
            $t->timestamps();
        });

        DB::table('orders')->insert([
            'id' => 1, 'service_id' => 8, 'link' => self::LINK, 'mode' => 'manual',
            'status' => 'in_progress', 'remains' => 100,
        ]);
        DB::table('telegram_tasks')->insert([
            'id' => self::TASK_ID, 'order_id' => 1, 'service_id' => 8,
            'action' => 'subscribe', 'status' => TelegramTask::STATUS_PENDING, 'payload' => '{}',
        ]);
    }

    private function claimPending(): ?array
    {
        $service = app(TelegramTaskClaimService::class);
        $m = new \ReflectionMethod($service, 'claimPendingTask');

        return $m->invoke($service, self::TASK_ID, 'subscribe', self::PHONE, TelegramPremiumTemplateScope::SCOPE_DEFAULT);
    }

    private function addMembership(string $state): void
    {
        DB::table('telegram_order_memberships')->insert([
            'order_id' => 1, 'service_id' => 8, 'account_phone' => self::PHONE,
            'link_hash' => TelegramAccountLinkState::linkHash(self::LINK),
            'link' => self::LINK, 'state' => $state, 'last_error' => 'join_invite_failed',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('blockingStates')]
    public function test_existing_membership_rejects_before_lease_and_cap(string $state): void
    {
        $this->addMembership($state);

        // Only the active-subscribed lookup may touch Redis; a lease/cap/cooldown
        // attempt would call other Redis methods and fail this mock.
        Redis::shouldReceive('get')->andReturn('0');

        $this->assertNull($this->claimPending());

        $task = DB::table('telegram_tasks')->where('id', self::TASK_ID)->first();
        $this->assertSame(TelegramTask::STATUS_PENDING, $task->status);
        $this->assertNull($task->leased_until);
        $this->assertSame('{}', $task->payload);
        $this->assertSame(1, DB::table('telegram_order_memberships')->count());
    }

    public static function blockingStates(): array
    {
        return [
            'failed' => ['failed'],
            'unsubscribed' => ['unsubscribed'],
            'subscribed' => ['subscribed'],
            'in_progress' => ['in_progress'],
        ];
    }
}
