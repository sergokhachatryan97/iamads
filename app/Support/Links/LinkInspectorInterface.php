<?php

namespace App\Support\Links;

/**
 * Contract for link inspectors. Each inspector validates URLs for a specific platform.
 *
 * @return array{valid: bool, error: string|null, kind: string|null}
 */
interface LinkInspectorInterface
{
    public function inspect(string $url): array;
}
