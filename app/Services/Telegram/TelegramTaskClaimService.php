<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Models\TelegramAccountLinkState;
use App\Models\TelegramFolderMembership;
use App\Models\TelegramOrderMembership;
use App\Models\TelegramTask;
use App\Support\TelegramPremiumTemplateScope;
use App\Support\TelegramSystemManagedTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Account-driven claim: performer sends phone, core returns tasks for that phone.
 * Uses telegram_order_memberships (order_id = orders.id) for subscribe dedupe and unsubscribe assignment.
 */
class TelegramTaskClaimService
{
    private const LEASE_TTL_SECONDS = 90;

    private const PHONE_ACTIVE_SUBSCRIBED_CAP = 500;

    private const PHONE_ACTIVE_SUBSCRIBED_CAP_PREMIUM = 1000;

    /**
     * Action-based rate limiting rules per scope.
     *
     * Each action defines:
     *   daily_cap       — max tasks per phone per day for this action
     *   cooldown_seconds — minimum gap between consecutive tasks of this action
     *
     * Actions not listed here fall back to the '_default' entry.
     * invite_subscribers shares the 'subscribe' bucket (normalized in normalizeAction).
     */
    private const ACTION_RULES = [
        TelegramPremiumTemplateScope::SCOPE_DEFAULT => [
            'subscribe'    => ['daily_cap' => 15,  'cooldown_seconds' => 1800],
            'unsubscribe'  => ['daily_cap' => 15,  'cooldown_seconds' => 1800],
            'bot_start'    => ['daily_cap' => 10, 'cooldown_seconds' => 600],
            'view'         => ['daily_cap' => 50, 'cooldown_seconds' => 10],
            'react'        => ['daily_cap' => 30, 'cooldown_seconds' => 30],
            'vote'         => ['daily_cap' => 20, 'cooldown_seconds' => 60],
            'repost'       => ['daily_cap' => 10, 'cooldown_seconds' => 300],
            'comment'      => ['daily_cap' => 10, 'cooldown_seconds' => 300],
            'story_repost' => ['daily_cap' => 15, 'cooldown_seconds' => 120],
            'story_like'   => ['daily_cap' => 30, 'cooldown_seconds' => 30],
            '_default'     => ['daily_cap' => 10, 'cooldown_seconds' => 300],
        ],
        TelegramPremiumTemplateScope::SCOPE_PREMIUM => [
            'subscribe'    => ['daily_cap' => 18,  'cooldown_seconds' => 1200],
            'unsubscribe'  => ['daily_cap' => 18,  'cooldown_seconds' => 1200],
            'bot_start'    => ['daily_cap' => 15, 'cooldown_seconds' => 300],
            'view'         => ['daily_cap' => 80, 'cooldown_seconds' => 5],
            'react'        => ['daily_cap' => 50, 'cooldown_seconds' => 20],
            'vote'         => ['daily_cap' => 30, 'cooldown_seconds' => 30],
            'repost'       => ['daily_cap' => 15, 'cooldown_seconds' => 180],
            'comment'      => ['daily_cap' => 15, 'cooldown_seconds' => 180],
            'story_repost' => ['daily_cap' => 20, 'cooldown_seconds' => 60],
            'story_like'   => ['daily_cap' => 50, 'cooldown_seconds' => 20],
            '_default'     => ['daily_cap' => 15, 'cooldown_seconds' => 180],
        ],
    ];

    public function __construct(
        private TelegramTaskService $taskService
    ) {}

    /**
     * Claim up to $limit tasks for the given phone. Priority: unsubscribe first, then subscribe.
     *
     * @return array<int, array{task_id: string, order_id: int, action: string, link: string|null, link_hash: string}>
     */
    public function claimForPhone(string $phone, int $limit = 1, string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT): array
    {
        $phone = TelegramAccountLinkState::normalizePhone($phone);

        $tasks = [];
        for ($i = 0; $i < $limit; $i++) {
            $taskDto = $this->claimSingle($phone, $scope);
            if ($taskDto === null) {
                break;
            }
            $tasks[] = $taskDto;
        }

        return $tasks;
    }

    /**
     * Claim one task for the phone: folder unsubscribe → regular unsubscribe → subscribe.
     */
    private function claimSingle(string $phone, string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT): ?array
    {
        // Priority 0: premium folder unsubscribe (any phone can claim, no per-phone membership)
        if ($scope === TelegramPremiumTemplateScope::SCOPE_PREMIUM) {
            $folderUnsub = $this->claimPremiumFolderUnsubscribe($phone);
            if ($folderUnsub !== null) {
                return $folderUnsub;
            }
        }

        // Priority 1: regular unsubscribe
        $unsubscribe = $this->claimUnsubscribe($phone, $scope);
        if ($unsubscribe !== null) {
            return $unsubscribe;
        }

        // Priority 2: subscribe
        return $this->claimSubscribe($phone, $scope);
    }

    /**
     * Premium folder unsubscribe: when a channel was removed from the shared folder,
     * tell the performer to leave that channel.
     *
     * Unlike regular orders, premium folder orders are system_managed — no per-phone
     * TelegramOrderMembership exists. Any performer phone can claim these.
     * Deduplication: one TelegramTask per (order_id + phone) prevents double-claiming.
     */
    private function claimPremiumFolderUnsubscribe(string $phone): ?array
    {
        $systemManagedKeys = TelegramSystemManagedTemplate::templateKeys();
        if ($systemManagedKeys === []) {
            return null;
        }

        // Find premium folder orders where channel was removed from folder
        $order = Order::query()
            ->where('execution_phase', Order::EXECUTION_PHASE_UNSUBSCRIBING)
            ->whereHas('service', function ($q) use ($systemManagedKeys) {
                $q->whereIn('template_key', $systemManagedKeys)
                    ->whereHas('category', function ($q2) {
                        $q2->where('link_driver', 'telegram');
                    });
            })
            ->whereHas('telegramFolderMembership', function ($q) {
                $q->where('status', TelegramFolderMembership::STATUS_REMOVED);
            })
            ->orderBy('id')
            ->first();

        if (! $order) {
            return null;
        }

        // Dedupe: skip if this phone already has a task for this order
        $alreadyClaimed = TelegramTask::query()
            ->where('order_id', $order->id)
            ->where('action', 'unsubscribe')
            ->whereJsonContains('payload->account_phone', $phone)
            ->exists();

        if ($alreadyClaimed) {
            return null;
        }

        $link = (string) $order->link;
        if (empty($link)) {
            return null;
        }

        $linkHash = TelegramAccountLinkState::linkHash($link);
        $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);

        $task = TelegramTask::create([
            'order_id' => $order->id,
            'subject_type' => Order::class,
            'subject_id' => $order->id,
            'action' => 'unsubscribe',
            'link_hash' => $linkHash,
            'telegram_account_id' => null,
            'provider_account_id' => null,
            'status' => TelegramTask::STATUS_LEASED,
            'leased_until' => $leasedUntil,
            'attempt' => 0,
            'payload' => [
                'link' => $link,
                'link_hash' => $linkHash,
                'action' => 'unsubscribe',
                'account_phone' => $phone,
                'subject' => ['type' => 'order', 'id' => $order->id],
                'premium_folder' => true,
            ],
        ]);

        Log::debug('Claimed premium folder unsubscribe task', [
            'task_id' => $task->id,
            'order_id' => $order->id,
            'phone' => $phone,
            'link' => $link,
        ]);

        return [
            'task_id' => $task->id,
            'order_id' => (int) $order->id,
            'action' => 'unsubscribe',
            'link' => $link,
            'link_hash' => $linkHash,
        ];
    }

    /**
     * Priority 1: return an unsubscribe task for this phone if order is unsubscribing and membership is subscribed.
     */
    private function claimUnsubscribe(string $phone, string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT): ?array
    {
        return DB::transaction(function () use ($phone, $scope): ?array {
            $membership = TelegramOrderMembership::query()
                ->where('account_phone', $phone)
                ->where('state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNull('unsubscribed_at')
                ->whereHas('order', function ($q) use ($scope) {
                    $q->where('execution_phase', Order::EXECUTION_PHASE_UNSUBSCRIBING)
                        ->whereHas('service', function ($q2) use ($scope) {
                            $q2->whereHas('category', function ($q3) {
                                $q3->where('link_driver', 'telegram');
                            });
                            TelegramPremiumTemplateScope::applyServiceTemplateScope($q2, $scope);
                        });
                })
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                return null;
            }

            $order = $membership->order;
            if (!$order || (int) ($order->remains ?? 0) < 0) {
                return null;
            }

            $dueAt = $order->completed_at->copy()
                ->addDays(max(1, (int)($order->service->duration_days ?? 1)));

            if (now()->lt($dueAt)) {
                return null;
            }

            $link = $order->link;
            $linkHash = TelegramAccountLinkState::linkHash($link);

            $action = 'unsubscribe';

            if ($this->tryIncrementPhoneDailyCap($phone, $scope, $action) === 0) {
                Log::debug('Claim denied: phone daily unsubscribe cap reached', ['phone' => $phone]);

                return null;
            }

            if (! $this->acquirePhoneCooldown($phone, $action, $this->getActionCooldownSeconds($scope, $action))) {
                Log::debug('Claim denied: phone cooldown active (unsubscribe)', ['phone' => $phone]);

                return null;
            }

            $payload = $this->buildClaimPayload($order, $action, $link, $linkHash, $phone);
            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);

            $task = TelegramTask::create([
                'order_id' => $order->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'action' => $action,
                'link_hash' => $linkHash,
                'telegram_account_id' => null,
                'provider_account_id' => null,
                'status' => TelegramTask::STATUS_LEASED,
                'leased_until' => $leasedUntil,
                'attempt' => 0,
                'payload' => array_merge($payload, ['account_phone' => $phone]),
            ]);

            $membership->update([
                'state' => TelegramOrderMembership::STATE_IN_PROGRESS,
                'unsubscribed_task_id' => $task->id,
            ]);

            $order->update(['status' => Order::STATUS_IN_PROGRESS]);

            Log::debug('Claimed unsubscribe task for phone', [
                'task_id' => $task->id,
                'order_id' => $order->id,
                'phone' => $phone,
            ]);

            $dto = [
                'task_id' => $task->id,
                'order_id' => (int) $order->id,
                'action' => $action,
                'link' => $link,
                'link_hash' => $linkHash,
            ];
            if (! empty($order->link_2)) {
                $dto['link_2'] = trim((string) $order->link_2);
            }

            return $dto;
        });
    }

    /**
     * Priority 2: return a subscribe task for this phone if an eligible order exists and daily quota allows.
     */
    private function claimSubscribe(string $phone, string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT): ?array
    {
        $now = now();

        $systemManagedKeys = TelegramSystemManagedTemplate::templateKeys();

        $orders = Order::query()
            ->with('service')
            ->whereIn('status', [Order::STATUS_AWAITING, Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING])
            ->where(function ($q) {
                $q->whereNull('execution_phase')->orWhere('execution_phase', Order::EXECUTION_PHASE_RUNNING);
            })
            ->where('remains', '>', 0)
            ->whereHas('service', function ($q) use ($scope, $systemManagedKeys) {
                $q->whereHas('category', function ($q2) {
                    $q2->where('link_driver', 'telegram');
                });
                TelegramPremiumTemplateScope::applyServiceTemplateScope($q, $scope);
                if ($systemManagedKeys !== []) {
                    $q->whereNotIn('template_key', $systemManagedKeys);
                }
            })
            ->orderBy('id')
            ->limit(200)
            ->get();

        $dueOrders = $orders
            ->filter(function (Order $o) use ($now) {
                $dueAt = $this->computeSubscribeDueAt($o);

                return $dueAt === null || $dueAt->lte($now);
            })
            ->sortBy(function (Order $o) {
                $dueAt = $this->computeSubscribeDueAt($o);

                return $dueAt ? $dueAt->getTimestamp() : 0;
            });

        foreach ($dueOrders as $order) {
            $result = $this->tryClaimSubscribeForOrder((int) $order->id, $phone, $scope);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Try to claim one subscribe task for a specific order. Returns null if not eligible or duplicate.
     */
    private function tryClaimSubscribeForOrder(int $orderId, string $phone, string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT): ?array
    {
        return DB::transaction(function () use ($orderId, $phone, $scope): ?array {
            $order = Order::query()
                ->where('id', $orderId)
                ->where('remains', '>', 0)
                ->whereHas('service', function ($q) use ($scope) {
                    $q->whereHas('category', function ($q2) {
                        $q2->where('link_driver', 'telegram');
                    });
                    TelegramPremiumTemplateScope::applyServiceTemplateScope($q, $scope);
                })
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                return null;
            }

            if (! TelegramPremiumTemplateScope::orderMatchesScope($order, $scope)) {
                return null;
            }

            $inFlight = TelegramOrderMembership::query()
                ->where('order_id', $order->id)
                ->where('state', TelegramOrderMembership::STATE_IN_PROGRESS)
                ->count();

            $target = $order->target_quantity;
            $currentTotal = (int) $order->delivered + $inFlight;
            if ($currentTotal >= $target) {
                Log::debug('Skip claim subscribe: current total >= target (overflow respected)', [
                    'order_id' => $order->id,
                    'in_flight' => $inFlight,
                    'delivered' => $order->delivered,
                    'target' => $target,
                ]);

                return null;
            }

            if (! $this->isOrderEligibleForSubscribe($order, $phone)) {
                return null;
            }

            if ($order->execution_phase === null) {
                $order->update(['execution_phase' => Order::EXECUTION_PHASE_RUNNING]);
                $order->refresh();
            }

            $link = $order->link;
            if (empty($link)) {
                return null;
            }

            $linkHash = TelegramAccountLinkState::linkHash($link);

            // Resolve action: service template -> execution_meta -> fallback 'subscribe'
            $action = $this->resolveOrderAction($order);

            /**
             * 1) READ/LOCK global row if exists, but DO NOT mutate yet.
             *    This prevents wasting cooldown/cap when global state already blocks the claim.
             */
            $global = TelegramAccountLinkState::query()
                ->where('account_phone', $phone)
                ->where('link_hash', $linkHash)
                ->lockForUpdate()
                ->first();

            if ($global !== null) {
                if (in_array($global->state, [
                    TelegramAccountLinkState::STATE_IN_PROGRESS,
                    TelegramAccountLinkState::STATE_SUBSCRIBED,
                ], true)) {
                    return null;
                }
            }

            // ---- DRIPFEED gating (do not consume caps/cooldown if not due) ----
            if ((bool) ($order->dripfeed_enabled ?? false)) {
                $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);
                $runIndex = (int) ($order->dripfeed_run_index ?? 0);
                $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
                $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);

                if ($runsTotal > 0 && $runIndex >= $runsTotal) {
                    return null; // dripfeed finished
                }

                if (! empty($order->dripfeed_next_run_at)) {
                    try {
                        if (Carbon::parse($order->dripfeed_next_run_at)->isFuture()) {
                            return null; // not due yet
                        }
                    } catch (\Throwable) {
                        // malformed => treat as due
                    }
                }

                // If run quota is filled, schedule next run and stop
                if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
                    $intervalMinutes = (int) ($order->dripfeed_interval_minutes ?? 0);
                    if ($intervalMinutes <= 0) {
                        $intervalMinutes = 60;
                    }

                    $order->update([
                        'dripfeed_run_index' => $runIndex + 1,
                        'dripfeed_delivered_in_run' => 0,
                        'dripfeed_next_run_at' => now()->addMinutes($intervalMinutes)->toDateTimeString(),
                    ]);

                    return null;
                }
            }

            /**
             * 2) ALL GATES BEFORE ANY DB MUTATION (prevents useless in_progress rows)
             */

            // Active membership cap: only enforced for subscribe-like and unsubscribe actions
            $normalizedAction = $this->normalizeAction($action);
            if (in_array($normalizedAction, ['subscribe', 'unsubscribe'], true)) {
                $activeSubscribedCap = $this->phoneActiveSubscribedCap($scope);
                $activeSubscribed = $this->getPhoneActiveSubscribedCount($phone);
                if ($activeSubscribed >= $activeSubscribedCap) {
                    Log::debug('Claim denied: phone active subscribed cap reached', [
                        'phone' => $phone,
                        'active_subscribed' => $activeSubscribed,
                        'cap' => $activeSubscribedCap,
                        'scope' => $scope,
                    ]);

                    return null;
                }
            }

            // Daily cap: do it BEFORE cooldown, so we don't set cooldown if cap is already exceeded.
            $capAction = $this->normalizeAction($action);
            if ($this->tryIncrementPhoneDailyCap($phone, $scope, $capAction) === 0) {
                Log::debug('Claim denied: phone daily cap reached', [
                    'phone' => $phone,
                    'action' => $action,
                    'cap_action' => $capAction,
                    'scope' => $scope,
                ]);

                return null;
            }

            // Cooldown must be acquired before task creation
            $cooldownSeconds = $this->getActionCooldownSeconds($scope, $capAction);
            if (! $this->acquirePhoneCooldown($phone, $capAction, $cooldownSeconds)) {
                Log::debug('Claim denied: phone cooldown active', [
                    'phone' => $phone,
                    'action' => $action,
                    'cooldown_seconds' => $cooldownSeconds,
                ]);

                return null;
            }

            /**
             * 3) Now mutate global/membership (safe: we already decided to create task)
             */
            if ($global !== null) {
                $global->update([
                    'state' => TelegramAccountLinkState::STATE_IN_PROGRESS,
                    'last_error' => null,
                ]);
            } else {
                try {
                    $global = TelegramAccountLinkState::create([
                        'account_phone' => $phone,
                        'link_hash' => $linkHash,
                        'state' => TelegramAccountLinkState::STATE_IN_PROGRESS,
                    ]);
                } catch (\Throwable $e) {
                    if (! $this->isDuplicateKeyException($e)) {
                        throw $e;
                    }
                    $global = TelegramAccountLinkState::query()
                        ->where('account_phone', $phone)
                        ->where('link_hash', $linkHash)
                        ->lockForUpdate()
                        ->first();

                    if ($global === null || in_array($global->state, [
                        TelegramAccountLinkState::STATE_IN_PROGRESS,
                        TelegramAccountLinkState::STATE_SUBSCRIBED,
                    ], true)) {
                        return null;
                    }

                    $global->update([
                        'state' => TelegramAccountLinkState::STATE_IN_PROGRESS,
                        'last_error' => null,
                    ]);
                }
            }

            try {
                $membership = TelegramOrderMembership::query()->create([
                    'order_id' => $order->id,
                    'account_phone' => $phone,
                    'link_hash' => $linkHash,
                    'link' => $link,
                    'state' => TelegramOrderMembership::STATE_IN_PROGRESS,
                ]);
            } catch (\Throwable $e) {
                if ($this->isDuplicateKeyException($e)) {
                    return null;
                }
                throw $e;
            }

            $payload = $this->buildClaimPayload($order, $action, $link, $linkHash, $phone);
            $leasedUntil = now()->addSeconds(self::LEASE_TTL_SECONDS);

            $task = TelegramTask::create([
                'order_id' => $order->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'action' => $action,
                'link_hash' => $linkHash,
                'telegram_account_id' => null,
                'provider_account_id' => null,
                'status' => TelegramTask::STATUS_LEASED,
                'leased_until' => $leasedUntil,
                'attempt' => 0,
                'payload' => array_merge($payload, ['account_phone' => $phone]),
            ]);

            if ((bool) ($order->dripfeed_enabled ?? false)) {
                $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
                $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);
                $runIndex = (int) ($order->dripfeed_run_index ?? 0);
                $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);

                $deliveredInRun++;

                $updates = ['dripfeed_delivered_in_run' => $deliveredInRun];

                // if quota reached, schedule next run
                if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
                    $intervalMinutes = (int) ($order->dripfeed_interval_minutes ?? 0);
                    if ($intervalMinutes <= 0) {
                        $intervalMinutes = 60;
                    }

                    $updates['dripfeed_run_index'] = $runIndex + 1;
                    $updates['dripfeed_delivered_in_run'] = 0;
                    $updates['dripfeed_next_run_at'] = now()->addMinutes($intervalMinutes)->toDateTimeString();

                    // optional: disable if finished
                    if ($runsTotal > 0 && ($runIndex + 1) >= $runsTotal) {
                        $updates['dripfeed_enabled'] = false;
                    }
                }

                $order->update($updates);
            }

            $global->update(['last_task_id' => $task->id]);
            $membership->update(['subscribed_task_id' => $task->id]);

            // next_run_at with speed_multiplier (DB independent)
            $providerPayload = $order->provider_payload ?? [];
            $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];

            $baseInterval = (int) ($executionMeta['interval_seconds'] ?? 30);
            $speed = $order->speed_multiplier ?? ($executionMeta['speed_multiplier'] ?? 1);
            $speed = (float) $speed;
            $speed = $speed > 0 ? $speed : 1.0;

            $effectiveInterval = (int) max(1, round($baseInterval / $speed));
            $executionMeta['next_run_at'] = now()->addSeconds($effectiveInterval)->toDateTimeString();

            $providerPayload['execution_meta'] = $executionMeta;
            $order->update([
                'status' => Order::STATUS_IN_PROGRESS,
                'provider_payload' => $providerPayload,
            ]);

            Log::debug('Claimed subscribe task for phone', [
                'task_id' => $task->id,
                'order_id' => $order->id,
                'phone' => $phone,
                'action' => $action,
            ]);

            $dto = [
                'task_id' => $task->id,
                'order_id' => (int) $order->id,
                'action' => $action,
                'link' => $link,
                'link_hash' => $linkHash,
            ];
            if (! empty($order->link_2)) {
                $dto['link_2'] = trim((string) $order->link_2);
            }

            return $dto;
        });
    }

    // -------------------------------------------------------------------------
    //  Action resolution
    // -------------------------------------------------------------------------

    /**
     * Normalize action string to its canonical cap/cooldown bucket.
     *
     * invite_subscribers is subscribe-like and shares the subscribe bucket.
     * Unknown actions pass through unchanged so they hit the _default rule.
     */
    private function normalizeAction(string $action): string
    {
        return match ($action) {
            'invite_subscribers' => 'subscribe',
            default => $action,
        };
    }

    /**
     * Resolve the effective action for an order.
     *
     * Priority: service template action -> execution_meta action -> 'subscribe' fallback.
     */
    private function resolveOrderAction(Order $order): string
    {
        $order->loadMissing('service');

        $templateAction = $order->service?->action();
        if ($templateAction !== null && $templateAction !== '') {
            return $templateAction;
        }

        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];

        $metaAction = (string) ($executionMeta['action'] ?? '');
        if ($metaAction !== '') {
            return $metaAction;
        }

        return 'subscribe';
    }

    // -------------------------------------------------------------------------
    //  Action rule helpers
    // -------------------------------------------------------------------------

    /**
     * Get the full rule array for a (scope, action) pair. Falls back to _default.
     *
     * @return array{daily_cap: int, cooldown_seconds: int}
     */
    private function getActionRule(string $scope, string $action): array
    {
        $scopeRules = self::ACTION_RULES[$scope] ?? self::ACTION_RULES[TelegramPremiumTemplateScope::SCOPE_DEFAULT];

        return $scopeRules[$action] ?? $scopeRules['_default'];
    }

    private function getActionDailyCap(string $scope, string $action): int
    {
        return (int) $this->getActionRule($scope, $action)['daily_cap'];
    }

    private function getActionCooldownSeconds(string $scope, string $action): int
    {
        return (int) $this->getActionRule($scope, $action)['cooldown_seconds'];
    }

    // -------------------------------------------------------------------------
    //  Rate limiting primitives (Redis-native)
    // -------------------------------------------------------------------------

    /**
     * Atomically increment daily cap counter for phone+scope+action.
     * Returns new count (1..cap) or 0 if cap exceeded.
     *
     * Key format: tg:phone:cap:{scope}:{action}:{phone}:{Y-m-d}
     */
    private function tryIncrementPhoneDailyCap(string $phone, string $scope, string $action): int
    {
        $date = Carbon::today()->format('Y-m-d');
        $key = "tg:phone:cap:{$scope}:{$action}:{$phone}:{$date}";
        $cap = $this->getActionDailyCap($scope, $action);
        $expireAt = Carbon::today()->endOfDay()->timestamp;

        $lua = <<<'LUA'
local cap = tonumber(ARGV[1])
local expire_at = tonumber(ARGV[2])
local v = redis.call('INCR', KEYS[1])
if v == 1 then redis.call('EXPIREAT', KEYS[1], expire_at) end
if v > cap then redis.call('DECR', KEYS[1]) return 0 end
return v
LUA;

        try {
            $result = Redis::eval($lua, 1, $key, $cap, $expireAt);

            return (int) $result;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Acquire per-action cooldown for this phone using atomic SET NX EX.
     * Returns true if cooldown was set (no prior cooldown), false if already in cooldown.
     *
     * Key format: tg:phone:cooldown:{action}:{phone}
     */
    private function acquirePhoneCooldown(string $phone, string $action, int $cooldownSeconds): bool
    {
        if ($cooldownSeconds <= 0) {
            return true;
        }

        $key = "tg:phone:cooldown:{$action}:{$phone}";

        try {
            $result = Redis::set($key, 1, 'EX', $cooldownSeconds, 'NX');

            return $result !== null && $result !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    //  Order eligibility & scheduling helpers (unchanged)
    // -------------------------------------------------------------------------

    private function computeSubscribeDueAt(Order $order): ?Carbon
    {
        // Dripfeed takes priority
        if ((bool) ($order->dripfeed_enabled ?? false)) {
            $v = $order->dripfeed_next_run_at ?? null;
            if (! $v) {
                return null;
            } // due now
            try {
                return Carbon::parse($v);
            } catch (\Throwable) {
                return null;
            }
        }

        // Fallback: provider_payload.execution_meta.next_run_at
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $nextRunAt = $executionMeta['next_run_at'] ?? null;

        if (! $nextRunAt) {
            return null;
        } // due now
        try {
            return Carbon::parse($nextRunAt);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isOrderEligibleForSubscribe(Order $order, string $phone): bool
    {
        $link = $order->link;
        if (empty($link)) {
            return false;
        }

        // next_run_at throttle
        $providerPayload = $order->provider_payload ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $nextRunAt = $executionMeta['next_run_at'] ?? null;

        if ($nextRunAt !== null) {
            try {
                if (\Carbon\Carbon::parse($nextRunAt)->isFuture()) {
                    return false;
                }
            } catch (\Throwable) {
                // safer: malformed => deny (prevents spam)
                return false;
            }
        }

        $linkHash = TelegramAccountLinkState::linkHash($link);

        // no duplicate membership for this phone/order/link
        return !TelegramOrderMembership::query()
            ->where('order_id', $order->id)
            ->where('account_phone', $phone)
            ->where('link_hash', $linkHash)
            ->whereIn('state', [
                TelegramOrderMembership::STATE_SUBSCRIBED,
                TelegramOrderMembership::STATE_IN_PROGRESS,
            ])
            ->exists();
    }

    private function buildClaimPayload(Order $order, string $action, ?string $link, string $linkHash, string $phone): array
    {
        $providerPayload = $order->provider_payload ?? [];
        $telegramData = $providerPayload['telegram'] ?? [];
        $executionMeta = is_array($providerPayload['execution_meta'] ?? null) ? $providerPayload['execution_meta'] : [];
        $parsed = is_array($telegramData['parsed'] ?? null) ? $telegramData['parsed'] : [];

        $perCall = (int) ($executionMeta['per_call'] ?? 1);

        $payload = [
            'link' => $link,
            'link_hash' => $linkHash,
            'action' => $action,
            'per_call' => $perCall,
            'meta' => $executionMeta,
            'parsed' => $parsed,
            'subject' => ['type' => 'order', 'id' => $order->id],
            'account_phone' => $phone,
        ];

        // invite_subscribers: include source link (link_2) for performer to use both links
//        if (!empty($order->link_2) && in_array($action, ['subscribe', 'invite_subscribers'], true)) {
//            $payload['link_2'] = trim((string) $order->link_2);
//        }

        return $payload;
    }

    // -------------------------------------------------------------------------
    //  Membership cap (subscribe/unsubscribe only)
    // -------------------------------------------------------------------------

    /**
     * Count active subscribed links for this phone (telegram_account_link_states.state = 'subscribed' only).
     */
    private function getPhoneActiveSubscribedCount(string $phone): int
    {
        return TelegramAccountLinkState::query()
            ->where('account_phone', $phone)
            ->where('state', TelegramAccountLinkState::STATE_SUBSCRIBED)
            ->count();
    }

    private function phoneActiveSubscribedCap(string $scope): int
    {
        return $scope === TelegramPremiumTemplateScope::SCOPE_PREMIUM
            ? self::PHONE_ACTIVE_SUBSCRIBED_CAP_PREMIUM
            : self::PHONE_ACTIVE_SUBSCRIBED_CAP;
    }

    private function isDuplicateKeyException(\Throwable $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'Duplicate entry') || str_contains($message, 'unique') || ($e->getCode() ?? 0) === 23000;
    }
}
