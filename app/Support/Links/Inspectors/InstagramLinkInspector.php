<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;

class InstagramLinkInspector implements LinkInspectorInterface
{
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        // instagram.com/p/..., instagram.com/reel/..., instagram.com/username, etc.
        $valid = (bool) preg_match(
            '#^(https?://)?(www\.)?(instagram\.com/|instagr\.am/)[^\s]+#i',
            $url
        );

        if (!$valid) {
            return ['valid' => false, 'error' => __('Invalid Instagram link format.'), 'kind' => null];
        }

        return ['valid' => true, 'error' => null, 'kind' => 'instagram'];
    }
}
