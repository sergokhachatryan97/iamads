<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;

class GenericUrlInspector implements LinkInspectorInterface
{
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        if (strlen($url) < 5) {
            return ['valid' => false, 'error' => __('home.link_error_other'), 'kind' => null];
        }

        $hasProtocol = (bool) preg_match('#^https?://#i', $url);
        $looksLikeDomain = (bool) preg_match('#^[a-zA-Z0-9][a-zA-Z0-9.-]*\.[a-zA-Z]{2,}#', $url);
        $valid = $hasProtocol || $looksLikeDomain;

        if (!$valid) {
            return ['valid' => false, 'error' => __('home.link_error_other'), 'kind' => null];
        }

        return ['valid' => true, 'error' => null, 'kind' => 'url'];
    }
}
