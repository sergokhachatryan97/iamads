<?php

namespace App\Support\Links\Inspectors;

use App\Support\Links\LinkInspectorInterface;

class YouTubeLinkInspector implements LinkInspectorInterface
{
    public function inspect(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['valid' => false, 'error' => __('home.link_error'), 'kind' => null];
        }

        $valid = (bool) preg_match(
            '#^(https?://)?(www\.)?(youtube\.com/(watch\?v=|shorts/|embed/)[A-Za-z0-9_\-]+|youtube\.com/@[A-Za-z0-9_.\-]+|youtube\.com/channel/UC[A-Za-z0-9_\-]+|youtube\.com/c/[A-Za-z0-9_.\-]+|youtu\.be/[A-Za-z0-9_\-]+)#i',
            $url
        );

        if (!$valid) {
            return ['valid' => false, 'error' => __('home.link_error_youtube'), 'kind' => null];
        }

        return ['valid' => true, 'error' => null, 'kind' => 'youtube'];
    }
}
