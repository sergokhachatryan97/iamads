<?php

namespace App\Support\Links\Inspectors;

use App\Services\App\AppLinkParser;
use App\Support\Links\LinkInspectorInterface;

/**
 * Validates app store URLs for category-based link validation (form/order creation).
 * Uses AppLinkParser for format validation only; full inspection is done by InspectAppLinkJob.
 */
class AppLinkInspector implements LinkInspectorInterface
{
    public function __construct(
        private AppLinkParser $parser
    ) {}

    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        $result = $this->parser->parse($url);

        if (!($result['ok'] ?? false)) {
            return [
                'valid' => false,
                'error' => $result['error'] ?? __('home.link_error'),
                'kind' => null,
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'kind' => 'app',
        ];
    }
}
