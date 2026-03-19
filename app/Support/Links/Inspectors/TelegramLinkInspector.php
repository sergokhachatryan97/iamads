<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;
use App\Support\TelegramLinkParser;

class TelegramLinkInspector implements LinkInspectorInterface
{
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        $parsed = TelegramLinkParser::parse($url);
        $kind = $parsed['kind'] ?? 'unknown';

        if ($kind === 'unknown') {
            return ['valid' => false, 'error' => __('Invalid Telegram link format.'), 'kind' => $kind];
        }
        if ($kind === 'special') {
            return ['valid' => false, 'error' => __('Link is not a joinable chat.'), 'kind' => $kind];
        }
        if ($kind === 'private_post') {
            return ['valid' => false, 'error' => __('Private post links are not supported.'), 'kind' => $kind];
        }

        return ['valid' => true, 'error' => null, 'kind' => $kind];
    }
}
