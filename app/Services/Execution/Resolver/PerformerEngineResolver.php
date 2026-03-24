<?php

namespace App\Services\Execution\Resolver;

use App\Services\Execution\Contracts\PerformerEngineInterface;
use App\Services\Execution\Engines\AppPerformerEngine;
use App\Services\Execution\Engines\GenericPerformerEngine;
use App\Services\Execution\Engines\TelegramPerformerEngine;
use App\Services\Execution\Engines\YouTubePerformerEngine;

/**
 * Resolves the performer engine by driver (category.link_driver).
 * For claim/report/check/ignore the driver is usually known from the route (e.g. provider/telegram → telegram).
 * Add new drivers here when you add new platforms (e.g. max => MaxPerformerEngine).
 *
 * Usage:
 * 1. Inject: public function __construct(private PerformerEngineResolver $performerResolver) {}
 * 2. Resolve: $engine = $this->performerResolver->resolve('telegram'); // or 'youtube'
 * 3. Use: $task = $engine->claim(['account_identity' => $phone]);
 *        $engine->report($taskId, ['state' => 'done', 'ok' => true, ...]);
 *        $engine->check($id, $context);  $engine->ignore($id, $context);
 */
class PerformerEngineResolver
{
    public function __construct(
        private TelegramPerformerEngine $telegramEngine,
        private YouTubePerformerEngine $youtubeEngine,
        private AppPerformerEngine $appEngine
    ) {}

    /**
     * Resolve performer engine by driver name (e.g. telegram, youtube, max).
     */
    public function resolve(string $driver): PerformerEngineInterface
    {
        return match ($driver) {
            'telegram' => $this->telegramEngine,
            'youtube' => $this->youtubeEngine,
            'app' => $this->appEngine,
            default => new GenericPerformerEngine($driver),
        };
    }
}
