<?php

namespace App\Jobs;

use App\Models\ProviderService;
use App\Models\Service;
use App\Services\Providers\AdtagClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Syncs Adtag provider service list into the local services table.
 * Queue-safe with lock; idempotent upsert by (provider, provider_service_id).
 */
class AdtagSyncServicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    public function __construct() {}

    public function handle(AdtagClient $client): void
    {
        $lockKey = 'adtag:sync:services';
        $lock = Cache::lock($lockKey, 120);

        if (!$lock->get()) {
            Log::debug('Adtag sync services skipped: another sync is running');
            return;
        }

        try {
            $providerName = config('providers.adtag.name', 'adtag');

            $items = $client->fetchServices();
            $created = 0;
            $updated = 0;
            $now = now();
            foreach ($items ?? [] as $s) {

                $rows[] = [
                    'provider_code' => $providerName,
                    'remote_service_id' => $s['service'],
                    'name' => (string)($s['name'] ?? ''),
                    'description' => (string)($s['description'] ?? ''),
                    'type' => $s['type'] ?? null,
                    'category' => $s['category'] ?? null,
                    'rate' => isset($s['rate']) ? (float)$s['rate'] : null,
                    'min' => isset($s['min']) ? (int)$s['min'] : null,
                    'max' => isset($s['max']) ? (int)$s['max'] : null,
                    'refill' => (bool)($s['refill'] ?? false),
                    'cancel' => (bool)($s['cancel'] ?? false),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                ProviderService::upsert(
                    $rows,
                    ['provider_code', 'remote_service_id'],
                    ['name', 'type', 'category', 'rate', 'min', 'max', 'refill', 'cancel', 'is_active', 'description', 'updated_at']
                );
            }


            Log::info('Adtag services sync completed', [
                'created' => $created,
                'updated' => $updated,
                'total_items' => count($items),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Heuristic mapping from provider name/category/description to template_key and service_type.
     * Only applied when template_key is empty or 'provider_unmapped'.
     *
     * @param array<string, mixed> $item
     * @return array{template_key: string, service_type: string, target_type: string}
     */
    private function mapTemplateFromProviderItem(array $item): array
    {
        $name = (string) ($item['name'] ?? '');
        $category = (string) ($item['category'] ?? '');
        $description = (string) ($item['description'] ?? '');
        $text = strtolower($name . ' ' . $category . ' ' . $description);

        $default = [
            'template_key' => 'provider_unmapped',
            'service_type' => 'provider_unmapped',
            'target_type' => 'telegram',
        ];

        // a) bot start with referral
        if (str_contains($text, 'bot start with referral') || (str_contains($text, 'bot start') && str_contains($text, 'referral'))) {
            return ['template_key' => 'bot_start_ref', 'service_type' => 'default', 'target_type' => Service::TARGET_TYPE_BOT];
        }
        // b) bot start
        if (str_contains($text, 'bot start')) {
            return ['template_key' => 'bot_start', 'service_type' => 'default', 'target_type' =>  Service::TARGET_TYPE_BOT];
        }
        // c) post views
        if (str_contains($text, 'post views') || (str_contains($text, 'views') && str_contains($text, 'post'))) {
            return ['template_key' => 'post_views', 'service_type' => 'default', 'target_type' => Service::TARGET_TYPE_CHANNEL];
        }
        // d) reactions
        if (str_contains($text, 'reactions') || str_contains($text, 'reaction')) {
            return ['template_key' => 'post_reactions', 'service_type' => 'default', 'target_type' => 'telegram'];
        }
        // e) channel subscribers
        if (str_contains($text, 'channel subscribers') || (str_contains($text, 'channel') && (str_contains($text, 'subscribers') || str_contains($text, 'members')))) {
            return ['template_key' => 'sub_channel', 'service_type' => 'default', 'target_type' => Service::TARGET_TYPE_CHANNEL];
        }
        // f) group members
        if (str_contains($text, 'group members') || (str_contains($text, 'group') && (str_contains($text, 'members') || str_contains($text, 'join')))) {
            return ['template_key' => 'join_group', 'service_type' => 'default', 'target_type' => Service::TARGET_TYPE_GROUP];
        }
        // g) premium members/subscribers
        if (str_contains($text, 'premium') && (str_contains($text, 'members') || str_contains($text, 'subscribers'))) {
            return ['template_key' => 'premium_members', 'service_type' => 'default', 'target_type' => 'telegram'];
        }

        return $default;
    }
}
