<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;

class TelegramLinkInspector
{
    public function __construct(
        protected TelegramHtmlParser $parser
    ) {}

    public function inspect(string $input): array
    {
        $url = $this->normalizeTelegramInputToUrl($input);

        if (!$url){
            return ['type'=>'unknown'];
        }
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 TelegramLinkInspector/1.0',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])
            ->get($url);

        return $this->parser->parse($url, $response->body());
    }

    protected function normalizeTelegramInputToUrl(string $input): ?string
    {
        $input = trim($input);

        // @username
        if (preg_match('/^@([A-Za-z0-9_]{3,32})$/', $input, $m)) {
            return 'https://t.me/' . $m[1];
        }

        // username only
        if (preg_match('/^[A-Za-z0-9_]{3,32}$/', $input)) {
            return 'https://t.me/' . $input;
        }

        // t.me/username
        if (preg_match('~^(?:www\.)?t\.me/(.+)$~i', $input)) {
            return 'https://t.me/' . preg_replace('~^(?:www\.)?t\.me/~i', '', $input);
        }

        // telegram.me/username
        if (preg_match('~^(?:www\.)?telegram\.me/(.+)$~i', $input)) {
            return 'https://t.me/' . preg_replace('~^(?:www\.)?telegram\.me/~i', '', $input);
        }

        // already full URL
        if (preg_match('~^https?://(?:www\.)?(?:t|telegram)\.me/.*$~i', $input)) {
            return $input;
        }

        // tg:// deep links
        if (preg_match('~^tg://~i', $input)) {
            return $input;
        }

        return null;
    }
}
