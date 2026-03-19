<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;

/**
 * Validates MAX Messenger links (max.ru, maxapp.ru, web.maxapp.ru).
 */
class MaxLinkInspector implements LinkInspectorInterface
{
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        // max.ru/username, maxapp.ru/invite/..., web.maxapp.ru/invite/...
        $valid = (bool) preg_match(
            '#^(https?://)?(www\.)?(max\.ru/[^\s]+|maxapp\.ru/[^\s]+|web\.maxapp\.ru/[^\s]+)#i',
            $url
        );

        if (!$valid) {
            return ['valid' => false, 'error' => __('Invalid MAX Messenger link format.'), 'kind' => null];
        }

        return ['valid' => true, 'error' => null, 'kind' => 'max'];
    }
}
