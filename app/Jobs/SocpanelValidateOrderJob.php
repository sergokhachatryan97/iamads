<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProviderOrder;
use App\Models\ProviderService;
use App\Services\OrderService;
use App\Services\Telegram\TelegramInspector;
use App\Support\TelegramExecutionPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SocpanelValidateOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 180;
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];
    private const SERVICE_RULES = [

        143 => ['mode' => 'chat_link_only_public_or_private', 'allow' => ['channel','supergroup','group'], 'audience' => null],

        // ✅ public post links - channel/supergroup ok
        86  => ['mode' => 'public_post', 'allow' => ['channel','supergroup'], 'audience' => null],
        131 => ['mode' => 'public_post', 'allow' => ['channel','supergroup'], 'audience' => null],
        132 => ['mode' => 'public_post', 'allow' => ['channel','supergroup'], 'audience' => null],

        // ✅ channel link mode:
        // accept: public channel, private channel invite, public group
        // reject: private group invite
        118 => ['mode' => 'channel_link', 'allow' => ['channel','supergroup'], 'audience' => ['subscribers','members']],
        119 => ['mode' => 'channel_link', 'allow' => ['channel','supergroup'], 'audience' => ['subscribers','members']],
        128 => ['mode' => 'channel_link', 'allow' => ['channel','supergroup'], 'audience' => ['subscribers','members']],
        129 => ['mode' => 'channel_link', 'allow' => ['channel','supergroup'], 'audience' => ['subscribers','members']],
        130 => ['mode' => 'channel_link', 'allow' => ['channel','supergroup'], 'audience' => ['subscribers','members']],
        133 => ['mode' => 'channel_link', 'allow' => ['channel','supergroup'], 'audience' => ['subscribers','members']],


        72  => ['mode' => 'chat_link_only_public_or_private', 'allow' => ['channel','supergroup', 'group'], 'audience' => null],

        // bots
        76  => ['mode' => 'bot_no_ref', 'allow' => ['bot'], 'audience' => null],
        148 => ['mode' => 'bot_no_ref', 'allow' => ['bot'], 'audience' => null],
        77  => ['mode' => 'bot_with_ref', 'allow' => ['bot'], 'audience' => null],

        // ✅ public channel link only => must be subscribers too
        146 => ['mode' => 'public_channel_link', 'allow' => ['channel','supergroup'], 'audience' => 'subscribers'],
    ];

    private function validateTelegramLinkByService(ProviderOrder $order, array $inspection): ?array
    {
        $serviceId = (int) ($order->remote_service_id ?? 0);

        $rule = self::SERVICE_RULES[$serviceId] ?? null;
        if (!$rule) {
            return ['code' => 'SERVICE_RULE_MISSING', 'message' => "No validation rule for service_id={$serviceId}"];
        }

        if (($inspection['ok'] ?? false) !== true) {
            return [
                'code' => $inspection['error_code'] ?? 'INVALID_LINK',
                'message' => $inspection['error'] ?? 'Invalid Telegram link',
            ];
        }

        $parsedKind = $inspection['parsed']['kind'] ?? null; // public_username | public_post | invite | bot_start | bot_start_with_referral ...
        $chatType   = $inspection['chat_type'] ?? null;      // channel | supergroup | group | bot
        $hasRef     = (bool)($inspection['parsed']['has_referrer'] ?? false);
        $audience   = $inspection['audience_type'] ?? null;

        // ----------------------------
        // 1) Enforce allowed chat types
        // ----------------------------
        if (!in_array($chatType, $rule['allow'], true)) {
            return [
                'code' => 'WRONG_CHAT_TYPE',
                'message' => "Expected ".implode(',', $rule['allow']).", got {$chatType}",
            ];
        }

        // ----------------------------
        // 2) Audience validation (string OR array)
        // ----------------------------
        if (!empty($rule['audience'])) {
            $expected = $rule['audience'];

            if (is_array($expected)) {
                if (!in_array($audience, $expected, true)) {
                    return [
                        'code' => 'WRONG_AUDIENCE',
                        'message' => "Expected audience in=".implode(',', $expected).", got {$audience}",
                    ];
                }
            } else {
                if ($audience !== $expected) {
                    return [
                        'code' => 'WRONG_AUDIENCE',
                        'message' => "Expected audience={$expected}, got {$audience}",
                    ];
                }
            }
        }

        $mode = (string) ($rule['mode'] ?? '');

        // ----------------------------
        // 3) Mode validation (link format)
        // ----------------------------

        if ($mode === 'public_post') {
            if ($parsedKind !== 'public_post') {
                return ['code' => 'WRONG_LINK_FORMAT', 'message' => 'Expected public post link like https://t.me/Channel/123'];
            }
            return null;
        }

        if ($mode === 'channel_link') {
            if (!in_array($parsedKind, ['public_username', 'invite'], true)) {
                return ['code' => 'WRONG_LINK_FORMAT', 'message' => 'Expected channel/group link (public username or invite), not a post/bot link'];
            }

            // ✅ KEY RULE:
            // invite + supergroup/group => private group invite => BLOCK
            // invite + channel          => private channel invite => OK
            // public_username + supergroup => public group link => OK
            if ($parsedKind === 'invite' && in_array($chatType, ['group', 'supergroup'], true)) {
                return [
                    'code' => 'PRIVATE_GROUP_NOT_ALLOWED',
                    'message' => 'Private group invite links are not allowed for this service. Use a public group link (https://t.me/GroupName) or a channel link.',
                ];
            }

            return null;
        }

        if ($mode === 'public_channel_link') {
            if ($parsedKind !== 'public_username') {
                return ['code' => 'WRONG_LINK_FORMAT', 'message' => 'Expected public channel link like https://t.me/ChannelName'];
            }
            return null;
        }

        if ($mode === 'bot_no_ref') {
            if ($parsedKind !== 'bot_start' || $hasRef) {
                return ['code' => 'WRONG_LINK_FORMAT', 'message' => 'Expected bot start link without referral'];
            }
            return null;
        }

        // ✅ referral OPTIONAL
        if ($mode === 'bot_with_ref') {
            if (!in_array($parsedKind, ['bot_start', 'bot_start_with_referral'], true)) {
                return ['code' => 'WRONG_LINK_FORMAT', 'message' => 'Expected bot link (with or without referral)'];
            }
            return null;
        }

        if ($mode === 'chat_public_or_private') {
            if (!in_array($parsedKind, ['public_username', 'invite'], true)) {
                return ['code' => 'WRONG_LINK_FORMAT', 'message' => 'Expected public chat link or invite link'];
            }
            return null;
        }

        if ($mode === 'chat_link_only_public_or_private') {
            if (!in_array($parsedKind, ['public_username', 'invite'], true)) {
                return [
                    'code' => 'WRONG_LINK_FORMAT',
                    'message' => 'For this service, only channel/group links are allowed (public username or invite). Post links are not allowed.',
                ];
            }
            return null;
        }

        return ['code' => 'RULE_ERROR', 'message' => "Unknown mode={$mode}"];
    }

    public function __construct(public int $serviceId, public string $normalizedLink) {}

    public function handle(TelegramInspector $inspector): void
    {
        Log::info('SocpanelValidateOrderJob dispatched');
        $groupKey = sha1($this->serviceId . '|' . $this->normalizedLink);

        // ✅ 1) hard lock՝ 1 worker per group
        $lock = Cache::lock("socpanel:validate-group:{$groupKey}", 90);
        if (!$lock->get()) {
            return;
        }

        $claimTtlMinutes = 10;
        $claimedAt = now();

        try {
            // ✅ 2) claim all eligible orders in this group
            // Claim criteria: validating, remains>0, and not claimed recently
            $claimedCount = ProviderOrder::query()
                ->where('status', Order::STATUS_VALIDATING)
                ->where('remains', '>', 0)
                ->where('remote_service_id', $this->serviceId)
                ->where('link', $this->normalizedLink)
                ->where(function ($q) use ($claimTtlMinutes) {
                    $q->whereNull('provider_sending_at')
                        ->orWhere('provider_sending_at', '<', now()->subMinutes($claimTtlMinutes));
                })
                ->update(['provider_sending_at' => $claimedAt]);

            if ($claimedCount === 0) {
                return;
            }

            // Fetch claimed ones (exactly those with provider_sending_at == $claimedAt)
            $orders = ProviderOrder::query()
                ->where('remote_service_id', $this->serviceId)
                ->where('link', $this->normalizedLink)
                ->where('status', Order::STATUS_VALIDATING)
                ->where('provider_sending_at', $claimedAt)
                ->get(['id', 'remote_service_id', 'link', 'status', 'provider_payload']);

            if ($orders->isEmpty()) {
                return;
            }

            // ✅ 3) inspect once per group with cache
            $cacheKey = 'tg:inspection:' . sha1($this->serviceId . '|' . $this->normalizedLink);

            $cached = Cache::get($cacheKey);
            if (is_array($cached) && array_key_exists('ok', $cached)) {
                $inspectionResult = $cached;
            } else {
                $inspectionResult = $inspector->inspect($this->normalizedLink);

                if (($inspectionResult['ok'] ?? false) === true) {
                    Cache::put($cacheKey, $inspectionResult, now()->addHours(6));
                } else {
                    Cache::put($cacheKey, $inspectionResult, now()->addMinutes(2));
                }
            }

            $dependsFailCodes = [
                'RESOLVE_FAILED',
                'INVITE_HASH_INVALID',
                'INVITE_HASH_EXPIRED',
                'PAID_MESSAGES',
                'INVALID_FORMAT',
            ];

            $temporaryCodes = [
                'MTPROTO_DEADLINE_EXCEEDED',
                'MTPROTO_THROTTLE_SLOT_UNAVAILABLE',
                'NO_AVAILABLE_ACCOUNTS',
                'WORKER_SHUTDOWN',
                'STREAM_CLOSED',
                'RESOLVE_TEMPORARY_UNAVAILABLE',
            ];


            if (($inspectionResult['ok'] ?? false) !== true) {
                $code = strtoupper((string)($inspectionResult['error_code'] ?? ''));
                $message = $inspectionResult['error'] ?? 'Validation failed';

                // 1) depends failed (non-retry)
                if (in_array($code, $dependsFailCodes, true)) {
                    ProviderOrder::query()
                        ->whereIn('id', $orders->pluck('id'))
                        ->update([
                            'status' => Order::DEPENDS_STATUS_FAILED,
                            'provider_last_error' => $message,
                            'provider_last_error_at' => now(),
                            'provider_payload' => $inspectionResult,
                            'provider_sending_at' => null,
                        ]);
                    return;
                }

                // 2) temporary infra (retry later) -> DO NOT fail the order
                if (in_array($code, $temporaryCodes, true)) {
                    ProviderOrder::query()
                        ->whereIn('id', $orders->pluck('id'))
                        ->update([
                            // keep validating so poller will pick it up again
                            'status' => Order::STATUS_VALIDATING,
                            'provider_last_error' => $message,
                            'provider_last_error_at' => now(),
                            'provider_payload' => $inspectionResult,
                            'provider_sending_at' => null,
                        ]);
                    return;
                }

                // 3) everything else -> FAIL
                ProviderOrder::query()
                    ->whereIn('id', $orders->pluck('id'))
                    ->update([
                        'status' => Order::STATUS_FAIL,
                        'provider_last_error' => $message,
                        'provider_last_error_at' => now(),
                        'provider_payload' => $inspectionResult,
                        'provider_sending_at' => null,
                    ]);

                return;
            }


            // ✅ 5) apply service rules once (use first order for rule selection)
            $first = $orders->first();
            $fail = $this->validateTelegramLinkByService($first, $inspectionResult);

            if ($fail && $fail['code'] != 'SERVICE_RULE_MISSING') {
                ProviderOrder::query()
                    ->whereIn('id', $orders->pluck('id'))
                    ->update([
                        'status' => Order::DEPENDS_STATUS_FAILED,
                        'provider_last_error' => $fail['message'],
                        'provider_last_error_at' => now(),
                        'provider_payload' => $inspectionResult,
                        'provider_sending_at' => null,
                    ]);
                return;
            }

            // ✅ 6) OK → mark all OK
            ProviderOrder::query()
                ->whereIn('id', $orders->pluck('id'))
                ->update([
                    'status' => Order::DEPENDS_STATUS_OK,
                    'provider_last_error' => null,
                    'provider_last_error_at' => null,
                    'provider_payload' => $inspectionResult,
                    'provider_sending_at' => null,
                ]);

        } catch (Throwable $e) {
            Log::error('SocpanelValidateOrderGroupJob failed', [
                'service_id' => $this->serviceId,
                'link' => $this->normalizedLink,
                'error' => $e->getMessage(),
            ]);

            // important: release claim for this batch so it can retry cleanly
            ProviderOrder::query()
                ->where('remote_service_id', $this->serviceId)
                ->where('link', $this->normalizedLink)
                ->where('provider_sending_at', $claimedAt)
                ->update(['provider_sending_at' => null]);

            throw $e;
        } finally {
            optional($lock)->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        try {
            ProviderOrder::query()
                ->where('remote_service_id', $this->serviceId)
                ->where('link', $this->normalizedLink)
                ->whereIn('status', [Order::STATUS_VALIDATING])
                ->update([
                    'status' => Order::STATUS_FAIL,
                    'provider_last_error' => $exception->getMessage(),
                    'provider_last_error_at' => now(),
                    'provider_sending_at' => null,
                ]);

            Log::error('SocpanelValidateOrderJob failed (group)', [
                'service_id' => $this->serviceId,
                'link' => $this->normalizedLink,
                'exception' => $exception->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SocpanelValidateOrderJob::failed crashed', [
                'service_id' => $this->serviceId,
                'link' => $this->normalizedLink,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
