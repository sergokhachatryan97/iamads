<?php

namespace App\Services\Execution\Engines;

use App\Services\Execution\Contracts\PerformerEngineInterface;
use Illuminate\Support\Facades\Log;

/**
 * Temporary fallback for performer platforms that are not implemented yet.
 * Used when category.link_driver is not 'telegram' (e.g. max, youtube).
 * Do not use for real execution – add a dedicated engine (e.g. MaxPerformerEngine) when ready.
 */
class GenericPerformerEngine implements PerformerEngineInterface
{
    public function __construct(
        private string $driver
    ) {}

    public function claim(array $context = []): ?array
    {
        Log::warning('GenericPerformerEngine: claim not implemented', [
            'driver' => $this->driver,
        ]);
        return null;
    }

    public function report(string $taskId, array $result): array
    {
        Log::warning('GenericPerformerEngine: report not implemented', [
            'driver' => $this->driver,
            'task_id' => $taskId,
        ]);
        return [
            'ok' => false,
            'error' => "Performer engine for [{$this->driver}] is not implemented yet.",
        ];
    }

    public function check(string $id, array $context = []): array
    {
        Log::warning('GenericPerformerEngine: check not implemented', [
            'driver' => $this->driver,
            'id' => $id,
        ]);
        return [
            'ok' => false,
            'error' => "Performer engine for [{$this->driver}] is not implemented yet.",
        ];
    }

    public function ignore(string $id, array $context = []): array
    {
        Log::warning('GenericPerformerEngine: ignore not implemented', [
            'driver' => $this->driver,
            'id' => $id,
        ]);
        return [
            'ok' => false,
            'error' => "Performer engine for [{$this->driver}] is not implemented yet.",
        ];
    }
}
