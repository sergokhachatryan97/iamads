<?php

namespace App\Services\Telegram;


use Amp\CancelledException;
use App\Models\MtprotoTelegramAccount;
use App\Services\Telegram\MtprotoClientFactory;
use App\Services\Telegram\ProxyHealthService;
use App\Support\TelegramChatType;
use danog\MadelineProto\RPCErrorException;
use Amp\SignalException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TelegramMtprotoPoolService
{

    private const MODE_INSPECT = 'inspect';
    private const MODE_HEAVY   = 'heavy';

    private const PENALTY_KEY_PREFIX = 'tg:acct:penalty:';

    public function __construct(
        private MtprotoClientFactory $factory,
        private ProxyHealthService $proxyHealth
    ) {}

    private function isDebugSelection(): bool
    {
        return (bool) config('telegram_mtproto.debug_selection', true);
    }

    private function logSelectionDebug(string $message, array $context = []): void
    {
        if (! $this->isDebugSelection()) {
            return;
        }
        Log::debug('[MTProto selection] ' . $message, $context);
    }

    /* ============================================================
     * PUBLIC API
     * ============================================================ */

    /**
     * Main primitive: resolve username by getInfo() only.
     * Returns unified contract.
     */
    public function getInfoByUsername(string $username): array
    {
        $username = $this->normalizeUsername($username);

        return $this->executeWithPool(function (MtprotoTelegramAccount $account, \danog\MadelineProto\API $madeline) use ($username) {

            $resolved = $this->resolveUsernameWithApi($madeline, $username);

            if (!($resolved['ok'] ?? false)) {
                return $resolved;
            }

            return $this->ok([
                'username'  => $username,
                'type'      => $resolved['type'] ?? 'unknown',
                'raw'       => $resolved['raw'] ?? null,        // full getInfo() payload
                'raw_chat'  => $resolved['raw_chat'] ?? null,   // Chat/User object
                'inputPeer' => $resolved['inputPeer'] ?? null,
            ]);
        }, mode: self::MODE_INSPECT);
    }


    /**
     * Determine if username is a bot (getInfo-only).
     */
    public function resolveIsBotByUsername(string $username): array
    {
        $username = $this->normalizeUsername($username);

        return $this->executeWithPool(function (MtprotoTelegramAccount $account, \danog\MadelineProto\API $madeline) use ($username) {

            $resolved = $this->resolveUsernameWithApi($madeline, $username);
            if (!($resolved['ok'] ?? false)) {
                return $resolved;
            }

            $info = is_array($resolved['raw'] ?? null) ? $resolved['raw'] : [];
            $chat = $info['Chat'] ?? $info['chat'] ?? [];
            $user = $info['User'] ?? $info['user'] ?? [];

            $isBot = (!empty($user['bot']) || !empty($chat['bot']));

            return $this->ok([
                'username'  => $username,
                'is_bot'    => (bool) $isBot,
                'type'      => $resolved['type'] ?? 'unknown',
                'raw'       => $info,
                'raw_chat'  => $resolved['raw_chat'] ?? null,
                'inputPeer' => $resolved['inputPeer'] ?? null,
            ]);
        }, mode: self::MODE_INSPECT);
    }

    /**
     * Check invite link using MTProto pool (messages.checkChatInvite).
     */
    public function checkInvite(string $hash): array
    {
        $hash = trim($hash);

        return $this->executeWithPool(function (MtprotoTelegramAccount $account, \danog\MadelineProto\API $madeline) use ($hash) {

            try {
                $result = $madeline->messages->checkChatInvite(['hash' => $hash]);
                Log::info('checkChatInvite', ['result' => $result]);

            } catch (RPCErrorException $e) {
                $rpc = strtoupper((string) ($e->rpc ?? ''));
                $msg = strtoupper((string) $e->getMessage());

                if (str_contains($rpc, 'INVITE_HASH_INVALID') || str_contains($msg, 'INVITE_HASH_INVALID')) {
                    return $this->fail('INVITE_HASH_INVALID', 'Invalid invite link');
                }

                if (str_contains($rpc, 'INVITE_HASH_EXPIRED') || str_contains($msg, 'INVITE_HASH_EXPIRED')) {
                    return $this->fail('INVITE_HASH_EXPIRED', 'Invite link expired');
                }

                // retry handled by pool if needed
                return $this->fail($rpc !== '' ? $rpc : 'MTPROTO_RPC', $e->getMessage(), [
                    'retryable' => true,
                ]);
            }

            if (!is_array($result) || !isset($result['_'])) {
                return $this->fail('MTPROTO_INVALID_RESPONSE', 'Invalid response from MTProto', [
                    'raw' => $result,
                ]);
            }

            $type = (string) $result['_'];

            // 1) chatInvite
            if ($type === 'chatInvite' || $type === 'chatInvitePeek') {
                $isPaid = isset($result['subscription_pricing']) && $result['subscription_pricing'] !== null;

                $isChannel = (($result['channel'] ?? false) === true || ($result['channel'] ?? null) === 1)
                    || (($result['broadcast'] ?? false) === true || ($result['broadcast'] ?? null) === 1);

                if ($isChannel) {
                    $peerType = !empty($result['megagroup']) ? 'supergroup' : 'channel';
                } else {
                    $peerType = 'group';
                }


                return $this->ok([
                    'title' => $result['title'] ?? null,
                    'participants_count' => $result['participants_count'] ?? null,
                    'chat_type' => $peerType,
                    'is_paid_join' => $isPaid,
                    'subscription_pricing' => $result['subscription_pricing'] ?? null,
                    'subscription_form_id' => $result['subscription_form_id'] ?? null,
                    'raw' => $result,
                ]);
            }

            // 2) chatInviteAlready
            if ($type === 'chatInviteAlready') {
                $chat = $result['chat'] ?? [];

                $peerType = 'group';
                if (is_array($chat) && (($chat['_'] ?? null) === 'channel')) {
                    $peerType = !empty($chat['megagroup']) ? 'supergroup' : 'channel';
                } elseif (is_array($chat) && (($chat['_'] ?? null) === 'chat')) {
                    $peerType = 'group';
                }

                return $this->ok([
                    'title' => is_array($chat) ? ($chat['title'] ?? null) : null,
                    'participants_count' => is_array($chat) ? ($chat['participants_count'] ?? null) : null,
                    'chat_type' => $peerType,
                    'is_paid_join' => false,
                    'raw' => $result,
                ]);
            }

            return $this->fail('MTPROTO_UNEXPECTED', 'Unexpected response constructor: ' . $type, [
                'raw' => $result,
            ]);
        }, mode: self::MODE_INSPECT);
    }

    /* ============================================================
     * INTERNAL: Pool execution
     * ============================================================ */


    private function selectAvailableAccount(
        array $excludeIds = [],
        string $mode = self::MODE_INSPECT,
        array $excludeProxyKeys = []
    ): ?MtprotoTelegramAccount {

        $batchSize = (int) config('telegram_mtproto.selection_batch_size', 20);
        $topK = (int) config('telegram_mtproto.selection_top_k', 7);
        $topK = $topK > 0 ? $topK : 7;
        $penaltyWeight = (int) config('telegram_mtproto.selection_penalty_weight', 1000);
        $now = now();

        $q = MtprotoTelegramAccount::query()
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', $now);
            });

        if ($mode === self::MODE_HEAVY) {
            $q->where('is_heavy', true);
            $q->where(function ($qq) use ($now) {
                $qq->whereNull('daily_heavy_reset_at')
                    ->orWhere('daily_heavy_reset_at', '<=', $now)
                    ->orWhereColumn('daily_heavy_used', '<', 'daily_heavy_cap');
            });
        } else {
            $q->where('is_inspect', true);
        }

        if ($excludeIds !== []) {
            $q->whereNotIn('id', $excludeIds);
        }

        $candidates = $q->orderByRaw('last_used_at IS NULL DESC')
            ->orderBy('last_used_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Compute proxy keys once
        $proxyKeys = $candidates->map(fn (MtprotoTelegramAccount $a) => $this->proxyThrottleKey($a));
        $uniqueProxyKeys = $proxyKeys->unique()->values();
        $monoculture = $uniqueProxyKeys->count() === 1;

        // ✅ Apply proxy cooldown filter ONLY if not monoculture
        if (! $monoculture) {
            $candidates = $candidates->reject(fn (MtprotoTelegramAccount $a) =>
            $this->proxyHealth->isOnCooldown($this->proxyThrottleKey($a))
            );
            if ($candidates->isEmpty()) {
                return null;
            }
        }

        // ✅ Apply excludeProxyKeys ONLY if not monoculture (monoculture would reject all)
        if (! $monoculture && $excludeProxyKeys !== []) {
            $candidates = $candidates->reject(function (MtprotoTelegramAccount $a) use ($excludeProxyKeys) {
                $pk = $this->proxyThrottleKey($a);
                return in_array($pk, $excludeProxyKeys, true);
            });
            if ($candidates->isEmpty()) {
                return null;
            }
        }

        // Sorting:
        // - monoculture => strict LRU + penalty (no shuffle, no topK randomness)
        // - multi-proxy => score-based + penalty + LRU, then topK shuffle
        if ($monoculture) {
            $candidates = $candidates->sort(function (MtprotoTelegramAccount $a, MtprotoTelegramAccount $b) use ($penaltyWeight) {
                $penA = $this->hasAccountPenalty($a->id) ? $penaltyWeight : 0;
                $penB = $this->hasAccountPenalty($b->id) ? $penaltyWeight : 0;

                if ($penA !== $penB) return $penA <=> $penB; // non-penalized first

                $usedA = $a->last_used_at?->getTimestamp() ?? 0;
                $usedB = $b->last_used_at?->getTimestamp() ?? 0;

                if ($usedA !== $usedB) return $usedA <=> $usedB;

                return $a->id <=> $b->id;
            })->values();

            return $candidates->first(); // ✅ strict LRU
        }

        $candidates = $candidates->sort(function (MtprotoTelegramAccount $a, MtprotoTelegramAccount $b) use ($penaltyWeight) {
            $scoreA = $this->proxyHealth->score($this->proxyThrottleKey($a));
            $scoreB = $this->proxyHealth->score($this->proxyThrottleKey($b));

            if ($this->hasAccountPenalty($a->id)) $scoreA -= $penaltyWeight;
            if ($this->hasAccountPenalty($b->id)) $scoreB -= $penaltyWeight;

            if ($scoreA !== $scoreB) return $scoreB <=> $scoreA;

            $usedA = $a->last_used_at?->getTimestamp() ?? 0;
            $usedB = $b->last_used_at?->getTimestamp() ?? 0;

            if ($usedA !== $usedB) return $usedA <=> $usedB;

            return $a->id <=> $b->id;
        })->values();

        $topCandidates = $candidates->take($topK);
        if ($topCandidates->count() > 1) {
            $topCandidates = $topCandidates->shuffle()->values();
        }

        return $topCandidates->first();
    }


    /**
     * Returns one candidate that would be eligible except for proxy cooldown (same query as selection,
     * but without proxy cooldown filter). Used when selectAvailableAccount returns null to decide
     * whether to wait for proxy cooldown before returning NO_AVAILABLE_ACCOUNTS.
     * @return array{account: MtprotoTelegramAccount, proxy_key: string}|null
     */
    private function getOneCandidateDroppedByProxyCooldownOnly(
        array $excludeIds,
        string $mode,
        array $excludeProxyKeys
    ): ?array {
        $batchSize = (int) config('telegram_mtproto.selection_batch_size', 20);
        $now = now();

        $q = MtprotoTelegramAccount::query()
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->where(function ($q) use ($now) {
                $q->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', $now);
            });

        if ($mode === self::MODE_HEAVY) {
            $q->where('is_heavy', true);
            $q->where(function ($qq) use ($now) {
                $qq->whereNull('daily_heavy_reset_at')
                    ->orWhere('daily_heavy_reset_at', '<=', $now)
                    ->orWhereColumn('daily_heavy_used', '<', 'daily_heavy_cap');
            });
        } else {
            $q->where('is_inspect', true);
        }

        if ($excludeIds !== []) {
            $q->whereNotIn('id', $excludeIds);
        }

        $candidates = $q->orderByRaw('last_used_at IS NULL DESC')
            ->orderBy('last_used_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($batchSize)
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        // Apply only excludeProxyKeys (no proxy cooldown filter)
        if ($excludeProxyKeys !== []) {
            $candidates = $candidates->reject(function (MtprotoTelegramAccount $a) use ($excludeProxyKeys) {
                $pk = $this->proxyThrottleKey($a);
                return in_array($pk, $excludeProxyKeys, true);
            });
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        $first = $candidates->first();
        $proxyKey = $this->proxyThrottleKey($first);
        if (! $this->proxyHealth->isOnCooldown($proxyKey)) {
            return null;
        }

        return ['account' => $first, 'proxy_key' => $proxyKey];
    }

    /**
     * Wait for proxy cooldown to expire, bounded by deadline. Returns true if cooldown expired (or nearly), false if deadline exceeded.
     */
    private function waitForProxyCooldownBounded(string $proxyKey, int $deadlineMs, int $startedAtMs): bool
    {
        $minRemainingMs = 200;
        $chunkMaxMs = 2000;

        while (true) {
            $elapsedMs = (int) (microtime(true) * 1000) - $startedAtMs;
            $remainingMs = $deadlineMs - $elapsedMs;
            if ($remainingMs < $minRemainingMs) {
                return false;
            }

            $cooldownSec = $this->proxyHealth->cooldownRemaining($proxyKey);
            if ($cooldownSec <= 0) {
                return true;
            }

            $waitMs = (int) min($remainingMs, $cooldownSec * 1000, $chunkMaxMs);
            $waitMs = max(100, $waitMs);
            $this->jitterSleepMs($waitMs, $waitMs + 100);
        }
    }

    /** Selection-only: do not write to DB. TTL from config (default 10–30 sec). */
    private function applyAccountLockPenalty(int $accountId): void
    {
        $ttl = (int) config('telegram_mtproto.lock_penalty_ttl_seconds', 20);
        $ttl = max(10, min(30, $ttl));
        Cache::put(self::PENALTY_KEY_PREFIX . $accountId, 1, $ttl);
    }

    private function hasAccountPenalty(int $accountId): bool
    {
        return Cache::has(self::PENALTY_KEY_PREFIX . $accountId);
    }
    private function markInfraFailure(MtprotoTelegramAccount $account, string $code, int $baseMinutes = 30): void
    {
        // escalation ըստ fail_count
        $fails = (int)($account->fail_count ?? 0);

        $minutes = match (true) {
            $fails >= 6 => max($baseMinutes, 180),
            $fails >= 3 => max($baseMinutes, 60),
            default     => $baseMinutes,
        };

        // ✅ Prefer disable + cooldown together
        try {
            $account->forceFill([
                'fail_count'     => $fails + 1,
                'cooldown_until' => now()->addMinutes($minutes),
                'disabled_at'    => now(), // time-based disabling
            ])->save();
        } catch (\Throwable $x) {
            // ignore
        }

        // if you keep recordFailure, make it lightweight (or remove)
        try { $account->recordFailure($code); } catch (\Throwable $x) {}
    }

    private function ensureHeavyDailyWindow(MtprotoTelegramAccount $account): void
    {
        $now = now();

        if (!$account->daily_heavy_reset_at || $account->daily_heavy_reset_at->isPast()) {
            $account->forceFill([
                'daily_heavy_used' => 0,
                'daily_heavy_reset_at' => $now->copy()->addDay()->startOfDay(), // next day 00:00
            ])->save();
        }
    }

    private function incrementHeavyUsed(MtprotoTelegramAccount $account): void
    {
        $this->ensureHeavyDailyWindow($account);
        $account->increment('daily_heavy_used');
    }

    private function executeWithPool(callable $callback, string $mode = self::MODE_INSPECT): array
    {
        $this->ensureRevoltErrorHandler();
        $this->revoltErrorHandlerSetBeforeStart();

        $rid = $this->newRid();

        $maxTries    = (int) config('telegram_mtproto.max_accounts_to_try_per_call', 4);
        $deadlineMs  = (int) (
            ($mode === self::MODE_INSPECT && (int) config('telegram_mtproto.call_deadline_inspect_ms', 0) > 0)
                ? config('telegram_mtproto.call_deadline_inspect_ms')
                : config('telegram_mtproto.call_deadline_ms', 30000)
        );
        $startedAtMs = (int) (microtime(true) * 1000);

        $excludeIds = [];
        $excludeProxyKeys = [];

        for ($attempt = 0; $attempt < $maxTries; $attempt++) {

            if ($this->deadlineExceeded($startedAtMs, $deadlineMs)) {
                return $this->failWithMeta('MTPROTO_DEADLINE_EXCEEDED', 'MTProto pool deadline exceeded', $rid, $mode, $attempt + 1, null, [
                    'reason' => 'deadline_exceeded',
                ]);
            }

            $account = $this->selectAvailableAccount($excludeIds, $mode, $excludeProxyKeys);

            if (!$account) {
                // Helpful reason: check if we'd have candidates but they are on proxy cooldown
                $cooldownCandidate = $this->getOneCandidateDroppedByProxyCooldownOnly($excludeIds, $mode, $excludeProxyKeys);

                if ($cooldownCandidate !== null) {
                    $pk = $cooldownCandidate['proxy_key'];
                    Log::info('NO_AVAILABLE_ACCOUNTS', [
                        'rid' => $rid,
                        'reason' => 'proxy_cooldown_all_dropped',
                        'mode' => $mode,
                        'attempt' => $attempt + 1,
                        'exclude_ids_count' => count($excludeIds),
                        'exclude_proxy_keys_count' => count($excludeProxyKeys),
                        'cooldown_proxy_key' => $pk,
                    ]);
                    return $this->failWithMeta('NO_AVAILABLE_ACCOUNTS', 'No available MTProto accounts', $rid, $mode, $attempt + 1, null, [
                        'reason' => 'proxy_cooldown_all_dropped',
                        'cooldown_proxy_key' => $pk,
                        'cooldown_remaining' => $this->proxyHealth->cooldownRemaining($pk),
                    ]);
                }

                Log::info('NO_AVAILABLE_ACCOUNTS', [
                    'rid' => $rid,
                    'reason' => 'db_query_empty_or_all_filtered',
                    'mode' => $mode,
                    'attempt' => $attempt + 1,
                    'exclude_ids_count' => count($excludeIds),
                    'exclude_proxy_keys_count' => count($excludeProxyKeys),
                ]);
                return $this->failWithMeta('NO_AVAILABLE_ACCOUNTS', 'No available MTProto accounts', $rid, $mode, $attempt + 1, null, [
                    'reason' => 'db_query_empty_or_all_filtered',
                ]);
            }

            $excludeIds[] = $account->id;

            $lockTtl = $this->computeAccountLockTtlSeconds();
            $accLockKey = "tg:mtproto:lock:{$account->id}";
            $accLock    = Cache::lock($accLockKey, $lockTtl);

            if (! $accLock->get()) {
                $this->applyAccountLockPenalty($account->id);
                $this->jitterSleepMs(8, 30);
                continue;
            }

            try {
                MtprotoTelegramAccount::whereKey($account->id)->update(['last_used_at' => now()]);

                // Proxy cooldown handling:
                // - in static multi-proxy world, this is meaningful
                // - in rotating/per-account keys, it won't kill everyone
                $proxyKey = $this->proxyThrottleKey($account);
                if ($this->proxyHealth->isOnCooldown($proxyKey)) {
                    // ✅ skip fast; do not "wait for cooldown" here (your requested behavior)
                    if (! in_array($proxyKey, $excludeProxyKeys, true)) {
                        $excludeProxyKeys[] = $proxyKey;
                    }
                    $this->jitterSleepMs(15, 60);
                    continue;
                }

                // Throttle: bounded for inspect so we don't block worker long
                if (!$this->waitForProxyThrottleByMode($account, $mode)) {
                    $accLock->release();
                    return $this->failWithMeta(
                        'MTPROTO_THROTTLE_SLOT_UNAVAILABLE',
                        'Proxy throttle slot not acquired in time',
                        $rid,
                        $mode,
                        $attempt + 1,
                        $account,
                        ['reason' => 'throttle_slot_timeout', 'retryable' => true]
                    );
                }
                $this->recordProxyCallWindow($account, $mode);
                $this->jitterSleepMs(50, 150);

                $madeline = $this->factory->makeForRuntime($account);

                $this->revoltErrorHandlerSetBeforeStart();
                $madeline->start();

                try {
                    $result = $callback($account, $madeline);
                } catch (\Throwable $e) {

                    $msg = strtoupper((string) $e->getMessage());

                    $isPeerDb =
                        str_contains($msg, 'THIS PEER IS NOT PRESENT IN THE INTERNAL PEER DATABASE') ||
                        str_contains($msg, 'INTERNAL PEER DATABASE') ||
                        str_contains($msg, 'PEER IS NOT PRESENT');

                    if ($isPeerDb) {
                        // ✅ 1) reset broken runtime instance
                        $this->factory->forgetRuntimeInstance($account);

                        // ✅ 2) fresh runtime
                        $madeline2 = $this->factory->makeForRuntime($account);
                        $this->revoltErrorHandlerSetBeforeStart();
                        $madeline2->start();

                        // ✅ 3) retry once; on second failure skip account (log and continue to next)
                        try {
                            $result = $callback($account, $madeline2);
                        } catch (\Throwable $e2) {
                            Log::warning('MTProto peer-db retry failed, skipping account', [
                                'rid' => $rid,
                                'account_id' => $account->id,
                                'proxy_key' => $proxyKey,
                                'reason' => 'peer_db_retry_failed',
                                'message' => substr((string) $e2->getMessage(), 0, 200),
                            ]);
                            $result = $this->fail(
                                'PEER_NOT_IN_DB',
                                $e2->getMessage(),
                                [
                                    'rid' => $rid,
                                    'mode' => $mode,
                                    'attempt' => $attempt + 1,
                                    'account_id' => $account->id,
                                    'proxy_key' => $proxyKey,
                                    'reason' => 'peer_db_retry_failed',
                                    'retryable' => true,
                                ]
                            );
                        }
                    } else {
                        throw $e;
                    }
                }

                // Attach meta context on all results
                $result['meta'] = array_merge($result['meta'] ?? [], [
                    'rid'       => $rid,
                    'mode'      => $mode,
                    'attempt'   => $attempt + 1,
                    'account_id'=> $account->id,
                    'proxy_key' => $proxyKey,
                ]);

                $callbackOk = ($result['ok'] ?? false) === true;
                if ($callbackOk) {
                    $account->recordSuccess();
                    $this->proxyHealth->markSuccess($proxyKey);

                    if ($mode === self::MODE_HEAVY) {
                        $this->incrementHeavyUsed($account);
                    }

                    return $result;
                }

                // retry decision centralized
                $errCode = strtoupper((string) ($result['error_code'] ?? ''));
                $retryableHint = (bool) ($result['meta']['retryable'] ?? false);
                $willRetry = $retryableHint || $this->isRetryableErrorCode($errCode);

                if ($willRetry) {
                    $account->recordFailure($errCode ?: 'CALL_FAILED');
                    $this->jitterSleepMs(20, 90);
                    continue;
                }

                return $result;

            } catch (RPCErrorException $e) {

                $this->factory->forgetRuntimeInstance($account);

                if ($sec = $this->parseFloodWaitSeconds($e->getMessage())) {
                    $cooldownSec = $sec + random_int(1, 5);
                    $proxyKey = $this->proxyThrottleKey($account);

                    $this->applyFloodWaitRate($account, $sec, $mode);
                    $account->setCooldown($cooldownSec);

                    return $this->failWithMeta('FLOOD_WAIT', "Flood wait {$sec}s", $rid, $mode, $attempt + 1, $account, [
                        'reason' => 'rpc_flood_wait',
                        'retryable' => true,
                        'flood_wait_sec' => $sec,
                        'cooldown_sec' => $cooldownSec,
                    ]);
                }

                $handled = $this->handleRpcError($account, $e, $mode);

                if (($handled['retry'] ?? false) === true) {
                    $this->jitterSleepMs(25, 120);
                    continue;
                }

                return $this->failWithMeta($handled['error_code'] ?? 'MTPROTO_RPC', $e->getMessage(), $rid, $mode, $attempt + 1, $account, [
                    'reason' => 'rpc_exception',
                ]);

            } catch (CancelledException $e) {

                $this->factory->forgetRuntimeInstance($account);

                $proxyKey = $this->proxyThrottleKey($account);
                $this->proxyHealth->markError($proxyKey, 'CANCELLED');
                try { $account->setCooldown(30); } catch (\Throwable $x) {}

                $this->jitterSleepMs(30, 120);
                continue;

            } catch (SignalException $e) {
                return $this->failWithMeta('WORKER_SHUTDOWN', 'SIGINT/SIGTERM received', $rid, $mode, $attempt + 1, $account, [
                    'reason' => 'signal_exception',
                ]);

            } catch (\Throwable $e) {

                $this->factory->forgetRuntimeInstance($account);

                $msg = (string) $e->getMessage();
                $proxyKey = $this->proxyThrottleKey($account);

                $isStreamish =
                    str_contains($msg, 'stream is not writable') ||
                    str_contains($msg, 'ClosedException') ||
                    str_contains($msg, 'Could not connect to MadelineProto') ||
                    str_contains($msg, 'IPC') ||
                    str_contains($msg, 'Unhandled future');

                if ($isStreamish) {
                    $this->proxyHealth->markError($proxyKey, 'STREAMISH');
                    try { $account->setCooldown($mode === self::MODE_HEAVY ? 120 : 60); } catch (\Throwable $x) {}
                    $this->jitterSleepMs(80, 200);
                    continue;
                }

                $this->handleGenericError($account, $e);
                $this->jitterSleepMs(25, 140);
                continue;

            } finally {
                try { $accLock->release(); } catch (\Throwable $e) {}
            }
        }

        Log::info('NO_AVAILABLE_ACCOUNTS', [
            'rid' => $rid,
            'reason' => 'all_attempts_exhausted',
            'mode' => $mode,
            'attempts' => $maxTries,
            'exclude_ids_count' => count($excludeIds),
            'exclude_proxy_keys_count' => count($excludeProxyKeys),
        ]);

        return $this->failWithMeta('NO_AVAILABLE_ACCOUNTS', 'All MTProto accounts exhausted or unavailable', $rid, $mode, $maxTries, null, [
            'reason' => 'all_attempts_exhausted',
            'exclude_ids_count' => count($excludeIds),
            'exclude_proxy_keys_count' => count($excludeProxyKeys),
        ]);
    }


    /* ============================================================
     * INTERNAL: Resolve username (getInfo only)
     * ============================================================ */

    private function resolveUsernameWithApi(\danog\MadelineProto\API $madeline, string $username): array
    {
        $username = $this->normalizeUsername($username);

        $info = $madeline->getInfo($username);
        Log::info('info', ['result' => $info]);


        $type = TelegramChatType::fromMadeline($info);

        $chat = $info['Chat'] ?? $info['chat'] ?? null;
        $user = $info['User'] ?? $info['user'] ?? null;

        $rawChat = is_array($chat) ? $chat : (is_array($user) ? $user : []);

        $inputPeer = $this->extractInputPeer($info, $rawChat);

        if (!$inputPeer) {
            return $this->fail('INPUT_PEER_MISSING', 'Could not extract inputPeer from getInfo() result', [
                'raw' => $this->thinInfoForLogs($info),
            ]);
        }

        return $this->ok([
            'type'      => $type ?? 'unknown',
            'raw'       => $info,
            'raw_chat'  => $rawChat,
            'inputPeer' => $inputPeer,
        ]);
    }

    /* ============================================================
     * INTERNAL: InputPeer extraction (keep compatible)
     * ============================================================ */

    private function extractInputPeer(array $info, array $chatFromInfo): ?array
    {
        // common wrappers
        $p = $info['InputPeer'] ?? $info['inputPeer'] ?? $info['peer'] ?? null;
        if (is_array($p) && isset($p['_'])) return $p;

        $p = $chatFromInfo['InputPeer'] ?? $chatFromInfo['inputPeer'] ?? $chatFromInfo['peer'] ?? null;
        if (is_array($p) && isset($p['_'])) return $p;

        // build peer from raw chat/user
        if (($chatFromInfo['_'] ?? null) === 'channel') {
            $id = $this->extractId($chatFromInfo);
            $ah = $chatFromInfo['access_hash'] ?? null;

            if ($id && $ah) {
                $mtprotoId = $this->normalizeBotApiIdToMtprotoId((int) $id);

                return [
                    '_' => 'inputPeerChannel',
                    'channel_id' => $mtprotoId,
                    'access_hash' => $ah,
                ];
            }
        }

        if (($chatFromInfo['_'] ?? null) === 'user') {
            $id = $this->extractId($chatFromInfo);
            $ah = $chatFromInfo['access_hash'] ?? null;

            if ($id && $ah) {
                return [
                    '_' => 'inputPeerUser',
                    'user_id' => (int) $id,
                    'access_hash' => $ah,
                ];
            }
        }

        if (($chatFromInfo['_'] ?? null) === 'chat') {
            $id = $this->extractId($chatFromInfo);
            if ($id) {
                $chatId = $this->normalizeBotApiIdToMtprotoId((int) $id);

                return [
                    '_' => 'inputPeerChat',
                    'chat_id' => $chatId,
                ];
            }
        }

        return null;
    }

    private function normalizeBotApiIdToMtprotoId(int $id): int
    {
        $s = (string) $id;

        if (str_starts_with($s, '-100')) {
            return (int) substr($s, 4);
        }

        if ($id < 0) return abs($id);

        return $id;
    }

    private function extractId(array $chat): ?int
    {
        $id = $chat['id']
            ?? $chat['channel_id']
            ?? $chat['chat_id']
            ?? null;

        if (!is_numeric($id)) return null;

        return (int) $id;
    }

    /* ============================================================
     * INTERNAL: Error handling
     * ============================================================ */

    private function handleRpcError(MtprotoTelegramAccount $account, RPCErrorException $e, string $mode = self::MODE_INSPECT): array
    {
        $rpc = strtoupper((string) ($e->rpc ?? ''));
        $msg = strtoupper((string) $e->getMessage());
        $code = $rpc !== '' ? $rpc : $msg;

        // FLOOD_WAIT_X
        if (preg_match('/FLOOD_WAIT_(\d+)/', $code, $m) || preg_match('/FLOOD_WAIT_(\d+)/', $msg, $m)) {
            $waitSeconds = (int) ($m[1] ?? 60);
            $waitSeconds = min($waitSeconds + 3, 3600);

            $this->applyFloodWaitRate($account, (int) ($m[1] ?? 60), $mode);
            $account->setCooldown($waitSeconds);

            Log::info('MTProto account hit FLOOD_WAIT', [
                'account_id' => $account->id,
                'wait_seconds' => $waitSeconds,
                'is_probe' => (bool) ($account->is_probe ?? false),
                'rpc' => $rpc,
            ]);

            return ['retry' => true, 'error_code' => 'FLOOD_WAIT'];
        }

        // permanent disable
        $permanent = [
            'AUTH_KEY_UNREGISTERED',
            'SESSION_REVOKED',
            'USER_DEACTIVATED',
            'PHONE_NUMBER_BANNED',
            'USER_DEACTIVATED_BAN',
        ];

        foreach ($permanent as $p) {
            if (str_contains($code, $p) || str_contains($msg, $p)) {
                $account->disable($p);

                if (method_exists($this->factory, 'forgetInstance')) {
                    $this->factory->forgetInstance($account->id);
                }

                Log::warning('MTProto account permanently disabled', [
                    'account_id' => $account->id,
                    'error_code' => $p,
                    'is_probe' => (bool) ($account->is_probe ?? false),
                ]);

                return ['retry' => true, 'error_code' => $p];
            }
        }

        // PEER_FLOOD
        if (str_contains($code, 'PEER_FLOOD') || str_contains($msg, 'PEER_FLOOD')) {
            $hours = !empty($account->is_probe) ? 6 : 3;
            $account->setCooldown($hours * 3600);

            Log::warning('MTProto account hit PEER_FLOOD', [
                'account_id' => $account->id,
                'cooldown_hours' => $hours,
                'is_probe' => (bool) ($account->is_probe ?? false),
            ]);

            return ['retry' => false, 'error_code' => 'PEER_FLOOD'];
        }

        // temp-ish
        $tempMarkers = ['RPC_CALL_FAIL', 'TIMEOUT', 'INTERNAL_SERVER_ERROR', 'SERVER_ERROR'];

        foreach ($tempMarkers as $tm) {
            if (str_contains($code, $tm) || str_contains($msg, $tm)) {
                $account->setCooldown(60);
                $account->recordFailure($tm);
                return ['retry' => true, 'error_code' => $tm];
            }
        }

        // default
        $errorCode = 'MTPROTO_RPC';
        $account->recordFailure($errorCode);
        $account->setCooldown(60);
        return ['retry' => true, 'error_code' => $errorCode];
    }

    private function handleGenericError(MtprotoTelegramAccount $account, \Throwable $e): void
    {
        if ($e instanceof \Amp\CancelledException || $e instanceof \Amp\SignalException) {
            Log::info('MTProto infra exception ignored', [
                'account_id' => $account->id,
                'type' => get_class($e),
                'msg' => $e->getMessage(),
            ]);
            return;
        }

        $msg = (string) $e->getMessage();
        $errorCode = $this->classifyGenericError($e, $msg);

        // STREAM_CLOSED / repeated stream errors: apply cooldown to avoid burst retries
        if ($errorCode === 'STREAM_CLOSED') {
            $account->setCooldown(60);
            $account->recordFailure($errorCode);
            Log::warning('MTProto account stream closed, cooldown applied', [
                'account_id' => $account->id,
                'error' => $msg,
            ]);
            return;
        }

        // SESSION_NOT_AUTHORIZED = no session file yet; do not record failure or disable (account needs authorization)
        if ($errorCode === 'SESSION_NOT_AUTHORIZED') {
            Log::info('MTProto account not yet authorized (no session file)', [
                'account_id' => $account->id,
                'error' => $msg,
            ]);
            return;
        }

        $account->recordFailure($errorCode);

        $fails = (int) ($account->fail_count ?? 0);
        $base  = 60;

        $mult = match (true) {
            $fails >= 6 => 8,
            $fails >= 3 => 4,
            $fails >= 1 => 2,
            default     => 1,
        };

        $cooldown = min(10 * 60, max($base, $base * $mult)) + random_int(0, 3);

        try { $account->setCooldown($cooldown); } catch (\Throwable $x) {}


        Log::warning('MTProto account error (generic)', [
            'account_id' => $account->id,
            'error_code' => $errorCode,
            'fail_count' => $account->fail_count,
            'error' => $msg,
        ]);
    }

    /**
     * Classify generic exception for logging and cooldown (FLOOD_WAIT, STREAM_CLOSED, SESSION_REVOKED, etc.).
     */
    private function classifyGenericError(\Throwable $e, string $msg): string
    {
        $upper = strtoupper($msg);
        $class = get_class($e);

        if (str_contains($upper, 'FLOOD_WAIT')) {
            return 'FLOOD_WAIT';
        }
        if (str_contains($upper, 'STREAM IS CLOSED') || str_contains($upper, 'THE STREAM IS CLOSED')
            || $class === \Amp\ByteStream\ClosedException::class || str_contains($upper, 'CLOSEDEXCEPTION')) {
            return 'STREAM_CLOSED';
        }
        if (str_contains($upper, 'SESSION_REVOKED') || str_contains($upper, 'AUTH_KEY_UNREGISTERED')) {
            return 'SESSION_REVOKED';
        }
        if (str_contains($upper, 'THIS PEER IS NOT PRESENT IN THE INTERNAL PEER DATABASE')
            || str_contains($upper, 'INTERNAL PEER DATABASE') || str_contains($upper, 'PEER IS NOT PRESENT')) {
            return 'PEER_NOT_IN_DB';
        }
        if (str_contains($upper, 'COULD NOT CONNECT TO MADELINEPROTO') || str_contains($upper, 'IPC')) {
            return 'IPC_UNAVAILABLE';
        }
        if (str_contains($upper, 'SESSION_NOT_AUTHORIZED')) {
            return 'SESSION_NOT_AUTHORIZED';
        }
        if ($e instanceof \Amp\TimeoutException || str_contains($upper, 'TIMEOUT')) {
            return 'RPC_TIMEOUT';
        }

        return 'GENERIC';
    }

    private function isRetryableErrorCode(string $errorCode): bool
    {
        $retryable = [
            'MTPROTO_ERROR',
            'REQUEST_FAILED',
            'INVALID_RESPONSE',
            'UNKNOWN_ERROR',
            'MTPROTO_RPC',
            'RPC_CALL_FAIL',
            'TIMEOUT',
            'INTERNAL_SERVER_ERROR',
            'SERVER_ERROR',
            'FLOOD_WAIT',
            'PEER_NOT_IN_DB'
        ];

        return in_array(strtoupper($errorCode), $retryable, true);
    }

    /* ============================================================
     * INTERNAL: Helpers
     * ============================================================ */

    private function normalizeUsername(string $username): string
    {
        return ltrim(strtolower(trim($username)), '@');
    }

    private function deadlineExceeded(int $startedAtMs, int $deadlineMs): bool
    {
        return (((int)(microtime(true) * 1000) - $startedAtMs) > $deadlineMs);
    }

    private function jitterSleepMs(int $minMs, int $maxMs): void
    {
        $ms = random_int($minMs, $maxMs);
        usleep($ms * 1000);
    }

    private function computeAccountLockTtlSeconds(): int
    {
        $jobTimeout = (int) config('telegram_mtproto.job_timeout_seconds', 60);
        $buffer     = (int) config('telegram_mtproto.lock_ttl_buffer_seconds', 60);
        $ttlCfg     = (int) config('telegram_mtproto.account_lock_ttl_seconds', 0);

        return $ttlCfg > 0 ? $ttlCfg : ($jobTimeout + $buffer);
    }

    /**
     * Normalize proxy settings so identical configs always produce the same hash.
     * Trims strings, casts port to int, empty strings -> null for stable json_encode.
     */
    private function normalizeProxySettings(MtprotoTelegramAccount $account): array
    {
        $trim = static function (?string $v): ?string {
            if ($v === null) {
                return null;
            }
            $v = trim((string) $v);
            return $v === '' ? null : $v;
        };
        $type = $trim($account->proxy_type ?? null);
        $host = $trim($account->proxy_host ?? null);
        if ($type === null || $host === null) {
            return ['_no_proxy' => true];
        }
        $port = $account->proxy_port;
        if ($port !== null && $port !== '') {
            $port = (int) $port;
        } else {
            $port = null;
        }
        return [
            'type' => $type,
            'host' => $host,
            'port' => $port,
            'user' => $trim($account->proxy_user ?? null),
            'pass' => $trim($account->proxy_pass ?? null),
            'secret' => $trim($account->proxy_secret ?? null),
        ];
    }

    /**
     * Base proxy key (no mode). Used for health/cooldown.
     * - no_proxy => "no_proxy:<account_id>" so one bad state never drops all no-proxy accounts.
     * - rotating => base + ":acct:<account_id>" so each account has its own throttle/cooldown.
     * - static => shared key from normalized settings (same settings => same key).
     */
    private function proxyThrottleKey(MtprotoTelegramAccount $account): string
    {
        $normalized = $this->normalizeProxySettings($account);
        if (! empty($normalized['_no_proxy'])) {
            return 'no_proxy:' . $account->id;
        }
        $base = substr(sha1(json_encode($normalized)), 0, 16);
        $mode = (string) config('telegram_mtproto.proxy_mode', 'rotating');
        if ($mode === 'rotating') {
            return $base . ':acct:' . $account->id;
        }
        return $base;
    }


    /**
     * Throttle key including mode so inspect and heavy have separate throttle budgets.
     */
    private function proxyThrottleKeyForMode(MtprotoTelegramAccount $account, string $mode): string
    {
        $base = $this->proxyThrottleKey($account);
        return $base . ':' . $mode;
    }

    /**
     * Block until this proxy+mode throttle allows one call, or maxWait elapsed.
     * Returns true if slot acquired, false if gave up (inspect uses short maxWait to avoid blocking worker).
     */
    private function waitForProxyThrottle(MtprotoTelegramAccount $account, string $mode): bool
    {
        $throttleKey = $this->proxyThrottleKeyForMode($account, $mode);
        $seconds = $this->getProxyThrottleSeconds($throttleKey);
        $cacheKey = 'tg:proxy:throttle:' . $throttleKey;

        $maxWait = $mode === self::MODE_INSPECT
            ? (int) config('telegram_mtproto.proxy_throttle_max_wait_inspect_sec', 8)
            : 60;
        $waited = 0.0;
        while ($waited < $maxWait) {
            if (Cache::add($cacheKey, 1, $seconds)) {
                return true;
            }
            $this->jitterSleepMs(150, 350);
            $waited += 0.25;
        }
        return false;
    }

    /**
     * Throttle interval in seconds for this proxy+mode: dynamic FLOOD_WAIT rate if set, else config default.
     */
    private function getProxyThrottleSeconds(string $throttleKeyWithMode): int
    {
        $stored = Cache::get('tg:proxy:floodrate:' . $throttleKeyWithMode);
        if (is_numeric($stored) && $stored > 0) {
            return (int) max(1, min(60, round($stored)));
        }
        $seconds = (int) config('telegram_mtproto.proxy_throttle_sec', 2);
        return $seconds > 0 ? $seconds : 2;
    }

    /**
     * Record this proxy+mode call in the current window (for FLOOD_WAIT rate: N calls in T sec, per mode).
     */
    private function recordProxyCallWindow(MtprotoTelegramAccount $account, string $mode): void
    {
        $throttleKey = $this->proxyThrottleKeyForMode($account, $mode);
        $key = 'tg:proxy:floodwindow:' . $throttleKey;
        $windowTtl = 300;
        $now = (int) floor(microtime(true));
        $window = Cache::get($key);
        if (! is_array($window) || (($window['start'] ?? 0) < $now - $windowTtl)) {
            $window = ['start' => $now, 'count' => 0];
        }
        $window['count'] = ((int) ($window['count'] ?? 0)) + 1;
        Cache::put($key, $window, $windowTtl);
    }

    /**
     * On FLOOD_WAIT_X: compute safe interval = (T+X)/N and store per proxy+mode.
     */
    private function applyFloodWaitRate(MtprotoTelegramAccount $account, int $xSeconds, string $mode): void
    {
        $throttleKey = $this->proxyThrottleKeyForMode($account, $mode);
        $windowKey = 'tg:proxy:floodwindow:' . $throttleKey;
        $rateKey = 'tg:proxy:floodrate:' . $throttleKey;
        $window = Cache::get($windowKey);
        if (! is_array($window)) {
            return;
        }
        $start = (int) ($window['start'] ?? 0);
        $n = (int) ($window['count'] ?? 1);
        $n = $n > 0 ? $n : 1;
        $t = (int) floor(microtime(true)) - $start;
        $t = $t > 0 ? $t : 1;
        $interval = ($t + $xSeconds) / $n;
        $interval = max(1.0, min(60.0, $interval));
        Cache::put($rateKey, $interval, 3600);
        Cache::forget($windowKey);

        Log::info('MTProto FLOOD_WAIT rate updated', [
            'proxy_key' => $throttleKey,
            'interval_sec' => round($interval, 2),
            'n_calls' => $n,
            't_sec' => $t,
            'x_sec' => $xSeconds,
        ]);
    }

    private function waitForProxyThrottleByMode(MtprotoTelegramAccount $account, string $mode): bool
    {
        return $this->waitForProxyThrottle($account, $mode);
    }

    /**
     * Wait for proxy throttle slot respecting a deadline. Returns true if slot acquired, false if deadline exceeded.
     * Uses same throttle key and TTL as waitForProxyThrottle (Cache::add loop with jitter).
     */
    private function waitForProxyThrottleBounded(
        MtprotoTelegramAccount $account,
        int $deadlineMs,
        int $startedAtMs,
        string $mode
    ): bool {
        $throttleKey = $this->proxyThrottleKeyForMode($account, $mode);
        $seconds = $this->getProxyThrottleSeconds($throttleKey);
        $cacheKey = 'tg:proxy:throttle:' . $throttleKey;
        $minRemainingMs = 200;

        while (true) {
            $elapsedMs = (int) (microtime(true) * 1000) - $startedAtMs;
            $remainingMs = $deadlineMs - $elapsedMs;
            if ($remainingMs < $minRemainingMs) {
                if ($this->isDebugSelection()) {
                    $this->logSelectionDebug('bounded wait exceeded deadline', [
                        'account_id' => $account->id,
                        'mode' => $mode,
                        'elapsed_ms' => $elapsedMs,
                        'deadline_ms' => $deadlineMs,
                    ]);
                }
                return false;
            }
            if (Cache::add($cacheKey, 1, $seconds)) {
                return true;
            }
            $this->jitterSleepMs(150, 350);
        }
    }

    /**
     * Set Revolt/EventLoop error handler so UnhandledFutureError (e.g. stream closed) is logged as warning.
     * Called at start of executeWithPool and again right before $madeline->start() to ensure we win over any other handler.
     */
    private function ensureRevoltErrorHandler(): void
    {
        if (!class_exists(\Revolt\EventLoop::class)) {
            return;
        }
        $this->setRevoltErrorHandler();
    }

    /**
     * Re-set Revolt error handler right before MTProto start so we definitely catch stream-closed errors.
     */
    private function revoltErrorHandlerSetBeforeStart(): void
    {
        if (!class_exists(\Revolt\EventLoop::class)) {
            return;
        }
        $this->setRevoltErrorHandler();
    }

    private function setRevoltErrorHandler(): void
    {
        try {
            \Revolt\EventLoop::setErrorHandler(function (\Throwable $e): void {
                $msg = (string) $e->getMessage();
                $cls = get_class($e);
                // Match by class or by message so we never miss (e.g. "Unhandled future" from Amp)
                $isUnhandledFuture = $e instanceof \Amp\Future\UnhandledFutureError
                    || str_contains($cls, 'UnhandledFutureError')
                    || stripos($msg, 'Unhandled future') !== false;
                if ($isUnhandledFuture) {
                    Log::warning('Revolt/EventLoop unhandled future (often after MTProto disconnect)', [
                        'message' => $msg,
                        'class' => $cls,
                    ]);
                    return;
                }
                Log::error('Revolt/EventLoop uncaught error', [
                    'message' => $msg,
                    'class' => $cls,
                ]);
            });
        } catch (\Throwable $e) {
            Log::debug('Revolt EventLoop setErrorHandler not available', ['msg' => $e->getMessage()]);
        }
    }

    /**
     * Keep logs light (avoid dumping huge objects).
     */
    private function thinInfoForLogs(array $info): array
    {
        $chat = $info['Chat'] ?? $info['chat'] ?? null;
        $user = $info['User'] ?? $info['user'] ?? null;

        $out = [
            'type' => $info['type'] ?? null,
        ];

        if (is_array($chat)) {
            $out['Chat'] = [
                '_' => $chat['_'] ?? null,
                'id' => $chat['id'] ?? null,
                'title' => $chat['title'] ?? null,
                'megagroup' => $chat['megagroup'] ?? null,
                'broadcast' => $chat['broadcast'] ?? null,
                'send_paid_messages_stars' => $chat['send_paid_messages_stars'] ?? null,
                'usernames' => $chat['usernames'] ?? null,
            ];
        }

        if (is_array($user)) {
            $out['User'] = [
                '_' => $user['_'] ?? null,
                'id' => $user['id'] ?? null,
                'username' => $user['username'] ?? null,
                'bot' => $user['bot'] ?? null,
            ];
        }

        return $out;
    }

    private function proxyCooldownKey(MtprotoTelegramAccount $account): string
    {
        return 'tg:proxy:cooldown:' . $this->proxyThrottleKey($account);
    }

    private function isProxyOnCooldown(MtprotoTelegramAccount $account): bool
    {
        return Cache::has($this->proxyCooldownKey($account));
    }

    private function putProxyCooldown(MtprotoTelegramAccount $account, int $seconds, string $reason): void
    {
        $seconds = max(10, $seconds);
        $ttl = $seconds + random_int(0, (int) floor($seconds * 0.2));
        Cache::put($this->proxyCooldownKey($account), $reason, now()->addSeconds($ttl));
    }

    private function proxyMode(): string
    {
        return (string) config('telegram_mtproto.proxy_mode', 'static'); // rotating|static
    }

    private function newRid(): string
    {
        try {
            return bin2hex(random_bytes(6));
        } catch (\Throwable $e) {
            return (string) mt_rand(100000, 999999);
        }
    }

    private function failWithMeta(
        string $code,
        string $message,
        string $rid,
        string $mode,
        int $attempt,
        ?MtprotoTelegramAccount $account = null,
        array $meta = []
    ): array {
        return $this->fail($code, $message, array_merge([
            'rid'       => $rid,
            'mode'      => $mode,
            'attempt'   => $attempt,
            'account_id'=> $account?->id,
            'proxy_key' => $account ? $this->proxyThrottleKey($account) : null,
        ], $meta));
    }

    private function ok(array $data = []): array
    {
        return array_merge(['ok' => true], $data);
    }

    private function fail(string $code, string $message, array $meta = []): array
    {
        $out = [
            'ok' => false,
            'error_code' => $code,
            'error' => $message,
        ];

        if (!empty($meta)) {
            $out['meta'] = $meta;
        }

        return $out;
    }

    private function parseFloodWaitSeconds(string $msg): ?int
    {
        if (preg_match('~FLOOD_(?:PREMIUM_)?WAIT_(\d+)~i', $msg, $m)) {
            $s = (int) $m[1];
            return min(max($s, 1), 3600);
        }
        return null;
    }

    private function withPeerDbRetryFreshRuntime(
        MtprotoTelegramAccount $account,
        \danog\MadelineProto\API $madeline,
        callable $fn
    ) {
        try {
            return $fn($madeline);
        } catch (\Throwable $e) {
            $msg = strtoupper((string) $e->getMessage());

            $isPeerDb =
                str_contains($msg, 'THIS PEER IS NOT PRESENT IN THE INTERNAL PEER DATABASE') ||
                str_contains($msg, 'INTERNAL PEER DATABASE') ||
                str_contains($msg, 'PEER IS NOT PRESENT');

            if (! $isPeerDb) {
                throw $e;
            }

            // ✅ fresh runtime retry once (peer DB might be busted on current runtime)
            $this->factory->forgetRuntimeInstance($account);

            $madeline2 = $this->factory->makeForRuntime($account);
            $this->revoltErrorHandlerSetBeforeStart();
            $madeline2->start();

            return $fn($madeline2);
        }
    }

}
