<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;

class FacebookLinkInspector implements LinkInspectorInterface
{
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        // facebook.com/..., fb.com/..., fb.watch/..., m.facebook.com/...
        $valid = (bool) preg_match(
            '#^(https?://)?(www\.|m\.)?(facebook\.com/|fb\.com/|fb\.watch/|fb\.me/)[^\s]+#i',
            $url
        );

        if (!$valid) {
            return ['valid' => false, 'error' => __('Invalid Facebook link format.'), 'kind' => null];
        }

        return ['valid' => true, 'error' => null, 'kind' => 'facebook'];
    }
}
