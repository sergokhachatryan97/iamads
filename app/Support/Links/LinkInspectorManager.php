<?php

namespace App\Support\Links;

use App\Support\Links\Inspectors\AppLinkInspector;
use App\Support\Links\Inspectors\GenericUrlInspector;
use App\Support\Links\Inspectors\TelegramLinkInspector;
use App\Support\Links\Inspectors\MaxLinkInspector;
use App\Support\Links\Inspectors\YouTubeLinkInspector;

/**
 * Resolves link inspectors by driver (link_driver) and runs validation.
 * Used for category-based link validation in orders.
 */
class LinkInspectorManager
{
    /** @var array<string, LinkInspectorInterface> */
    private array $inspectors = [];

    /**
     * Inspect a URL using the given driver. Falls back to generic if driver is unknown.
     *
     * @return array{valid: bool, error: string|null, kind: string|null}
     */
    public function inspect(string $driver, string $url): array
    {
        $inspector = $this->inspectorFor($driver);

        return $inspector->inspect($url);
    }

    /**
     * Get the inspector instance for the given driver.
     * Missing or null driver falls back to generic.
     */
    public function inspectorFor(string $driver): LinkInspectorInterface
    {
        $driver = $driver === '' ? 'generic' : $driver;

        if (!isset($this->inspectors[$driver])) {
            $this->inspectors[$driver] = $this->resolve($driver);
        }

        return $this->inspectors[$driver];
    }

    private function resolve(string $driver): LinkInspectorInterface
    {
        return match ($driver) {
            'telegram' => new TelegramLinkInspector(),
            'youtube' => new YouTubeLinkInspector(),
            'app' => new AppLinkInspector(app(\App\Services\App\AppLinkParser::class)),
            'max' => new MaxLinkInspector(),
            default => new GenericUrlInspector(),
        };
    }
}
