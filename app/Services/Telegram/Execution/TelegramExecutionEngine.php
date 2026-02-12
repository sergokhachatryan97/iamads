<?php

namespace App\Services\Telegram\Execution;

use App\Services\Telegram\Execution\Executors\BotStartExecutor;
use App\Services\Telegram\Execution\Executors\CommentExecutor;
use App\Services\Telegram\Execution\Executors\JoinExecutor;
use App\Services\Telegram\Execution\Executors\ReactExecutor;
use App\Services\Telegram\Execution\Executors\StoryReactExecutor;
use App\Services\Telegram\Execution\Executors\SubscribeExecutor;
use App\Services\Telegram\Execution\Executors\ViewExecutor;
use danog\MadelineProto\API;
use Illuminate\Support\Facades\Log;

/**
 * Execution engine that dispatches task actions to registered executors.
 */
class TelegramExecutionEngine
{
    /** @var array<string, TelegramActionExecutorInterface> */
    private array $executors = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    private function registerDefaults(): void
    {
        $this->register('bot_start', new BotStartExecutor());
        $this->register('subscribe', new SubscribeExecutor());
        $this->register('join', new JoinExecutor());
        $this->register('view', new ViewExecutor());
        $this->register('react', new ReactExecutor());
        $this->register('comment', new CommentExecutor());
        $this->register('story_react', new StoryReactExecutor());
    }

    public function register(string $action, TelegramActionExecutorInterface $executor): void
    {
        $this->executors[$action] = $executor;
    }

    /**
     * Execute task action.
     *
     * @param string $action Action key (e.g. subscribe, view)
     * @param API $madeline MadelineProto API
     * @param array $payload Task payload
     * @return array{ok: bool, error?: string, state?: string, retry_after?: int, data?: array}
     */
    public function execute(string $action, API $madeline, array $payload): array
    {
        $executor = $this->executors[$action] ?? null;

        if (!$executor) {
            Log::warning('No executor registered for action', ['action' => $action]);
            return [
                'ok' => false,
                'error' => "Unknown action: {$action}",
                'state' => 'done',
            ];
        }

        try {
            return $executor->handle($madeline, $payload);
        } catch (\InvalidArgumentException $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'state' => 'done',
            ];
        }
    }
}
