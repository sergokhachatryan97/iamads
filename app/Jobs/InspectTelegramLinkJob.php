<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\Telegram\TelegramInspector;
use App\Support\TelegramExecutionPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InspectTelegramLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $timeout = 90;
    public int $tries = 5;
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public int $orderId) {}

    public function handle(TelegramInspector $inspector, OrderService $orderService): void
    {
        // 1) Atomically "claim" the order (with TTL to recover stuck claims)
        $claimTtlMinutes = 10;

        $claimed = Order::query()
            ->whereKey($this->orderId)
            ->where('status', Order::STATUS_VALIDATING)
            ->where(function ($q) use ($claimTtlMinutes) {
                $q->whereNull('provider_sending_at')
                    ->orWhere('provider_sending_at', '<', now()->subMinutes($claimTtlMinutes));
            })
            ->update([
                'provider_sending_at' => now(),
            ]);

        if ($claimed === 0) {
            // Already being processed OR status changed OR doesn't exist OR claim not expired yet
            return;
        }

        $order = Order::query()->with('service')->find($this->orderId);

        if (!$order) {
            Log::warning('Order not found after claim (unexpected)', ['order_id' => $this->orderId]);
            // Can't release claim because order row doesn't exist
            return;
        }

        try {
            // Extra idempotency guard
            if ($order->status !== Order::STATUS_VALIDATING) {
                $this->releaseClaim($order);
                return;
            }

            // Ensure service is loaded
            if (!$order->service) {
                $providerPayload = $order->provider_payload ?? [];
                if (!is_array($providerPayload)) $providerPayload = [];

                $providerPayload['telegram'] = [
                    'ok' => false,
                    'error_code' => 'SERVICE_NOT_FOUND',
                    'error' => 'Service not found for order',
                ];

                $order->update([
                    'status' => Order::STATUS_INVALID_LINK,
                    'provider_last_error' => 'Service not found for order',
                    'provider_last_error_at' => now(),
                    'provider_sending_at' => null,
                    'provider_payload' => $providerPayload,
                ]);

                // ✅ refund (final invalid)
                $orderService->refundInvalid($order, 'Service not found for order');
                return;
            }

            $serviceType = $order->service->service_type ?? 'default';

            // 2) Inspect the link
            $inspectionResult = $inspector->inspect($order->link ?? '');

            // 3) Merge inspection result into provider_payload (do NOT save yet; we will update once per branch)
            $providerPayload = $order->provider_payload ?? [];
            if (!is_array($providerPayload)) $providerPayload = [];
            $providerPayload['telegram'] = $inspectionResult;

            // 4) Extract and store post_id for post-related services (react, comment, view)
            $parsed = $inspectionResult['parsed'] ?? [];
            if (isset($parsed['post_id']) && isset($parsed['kind']) && $parsed['kind'] === 'public_post') {
                $providerPayload['telegram']['post_id'] = (int) $parsed['post_id'];
            }

            // 4) Validate against service template rules (if template exists)
            $template = $order->service->template();

            if ($template) {
                $validationErrors = $this->validateAgainstTemplate($inspectionResult, $template);
                if (!empty($validationErrors)) {
                    $errorMessage = implode('; ', $validationErrors);

                    $order->update([
                        'status' => Order::STATUS_INVALID_LINK,
                        'provider_last_error' => $errorMessage,
                        'provider_last_error_at' => now(),
                        'provider_sending_at' => null,
                        'provider_payload' => $providerPayload,
                    ]);

                    // ✅ refund (final invalid)
                    $orderService->refundInvalid($order, $errorMessage);

                    Log::warning('Order failed template validation', [
                        'order_id' => $this->orderId,
                        'template_key' => $order->service->template_key,
                        'errors' => $validationErrors,
                    ]);

                    return;
                }
            }

            // 5) Handle inspection failure
            if (!($inspectionResult['ok'] ?? false)) {
                $errorCode = (string) ($inspectionResult['error_code'] ?? 'UNKNOWN_ERROR');
                $errorMessage = (string) ($inspectionResult['error'] ?? 'Unknown error during Telegram link inspection');

                // Decide if this is retryable (transient)
                if ($this->isRetryableInspectionError($errorCode)) {
                    // Keep VALIDATING so a retry can re-process it
                    $order->update([
                        'status' => Order::STATUS_VALIDATING,
                        'provider_last_error' => $errorMessage,
                        'provider_last_error_at' => now(),
                        'provider_sending_at' => null, // release claim
                        'provider_payload' => $providerPayload,
                    ]);

                    Log::warning('Telegram link inspection transient failure; will retry', [
                        'order_id' => $this->orderId,
                        'error_code' => $errorCode,
                        'error' => $errorMessage,
                    ]);

                    // Throw to trigger queue retry/backoff
                    throw new \RuntimeException("Retryable inspection error: {$errorCode} - {$errorMessage}");
                }

                // Non-retryable -> set final status
                $newStatus = Order::STATUS_INVALID_LINK;
                if (strtoupper($errorCode) === 'RESTRICTED') {
                    $newStatus = Order::STATUS_RESTRICTED;
                }

                $order->update([
                    'status' => $newStatus,
                    'provider_last_error' => $errorMessage,
                    'provider_last_error_at' => now(),
                    'provider_sending_at' => null, // release claim
                    'provider_payload' => $providerPayload,
                ]);

                // ✅ refund (final invalid/restricted)
                $orderService->refundInvalid($order, $errorMessage);

                Log::info('Telegram link inspection failed (final)', [
                    'order_id' => $this->orderId,
                    'error_code' => $errorCode,
                    'error' => $errorMessage,
                    'status' => $newStatus,
                ]);

                return;
            }

            // 6) Success: Calculate execution metadata and transition to awaiting
            $linkType = TelegramExecutionPolicy::linkTypeFromInspection($inspectionResult);

            // Determine policy from template map
            $policy = null;
            if ($template) {
                $policyKey = $template['policy_key'] ?? null;
                $action = $template['action'] ?? null;

                if ($policyKey && $action) {
                    $executionPolicyMap = config('telegram.execution_policy_map', []);
                    $linkTypePolicyMap = $executionPolicyMap[$policyKey] ?? [];

                    // Map chat_type to policy link_type if needed
                    $policyLinkType = $linkType;

                    // Handle supergroup/group mapping
                    if ($linkType === 'supergroup' && !isset($linkTypePolicyMap['supergroup'])) {
                        $policyLinkType = 'group';
                    } elseif ($linkType === 'group' && !isset($linkTypePolicyMap['group'])) {
                        $policyLinkType = 'supergroup';
                    }

                    $policy = $linkTypePolicyMap[$policyLinkType] ?? $linkTypePolicyMap[$linkType] ?? null;
                }
            }

            // Fallback to old logic if no template policy (backward compatibility)
            if (!$policy) {
                $policy = TelegramExecutionPolicy::policyFor($serviceType, $linkType);
            }

            // If no policy found or link type is unknown, mark as invalid (final)
            if (!$policy || $linkType === 'unknown') {
                $msg = "No execution policy for this service/template and link type '{$linkType}'";

                $order->update([
                    'status' => Order::STATUS_INVALID_LINK,
                    'provider_last_error' => $msg,
                    'provider_last_error_at' => now(),
                    'provider_sending_at' => null,
                    'provider_payload' => $providerPayload,
                ]);

                // ✅ refund (final invalid)
                $orderService->refundInvalid($order, $msg);

                Log::warning('No execution policy found', [
                    'order_id' => $this->orderId,
                    'template_key' => $order->service->template_key ?? null,
                    'link_type' => $linkType,
                ]);

                return;
            }

            // Calculate execution metadata using new interval formula
            $action = (string) ($policy['action'] ?? 'subscribe');
            $perCall = (int) ($policy['per_call'] ?? 1);
            $speedTier = (string) ($order->speed_tier ?? 'normal');
            $speedMultiplier = $order->service->getSpeedMultiplier($speedTier);
            $memberCount = (int) ($inspectionResult['member_count'] ?? 0);

            // Calculate interval using qty tiers + member_count + speed_tier
            $intervalData = $this->calculateInterval(
                $order->quantity,
                $perCall,
                $action,
                $memberCount,
                $speedTier,
                $speedMultiplier,
                $template['policy_key'] ?? null
            );

            $intervalSeconds = $intervalData['interval_seconds'];
            $stepsTotal = $intervalData['steps_total'];
            $etaSeconds = $intervalData['eta_seconds'];
            $etaAt = now()->addSeconds($etaSeconds);

            // Store policy snapshot for deterministic execution
            $policySnapshot = [
                'action' => $action,
                'interval_seconds' => $intervalSeconds,
                'per_call' => $perCall,
                'policy_key' => $template['policy_key'] ?? null,
                'link_type' => $linkType,
                'template_key' => $order->service->template_key ?? null,
                'speed_tier' => $speedTier,
                'speed_multiplier' => $speedMultiplier,
            ];

            // Initialize dripfeed metadata if enabled
            $dripfeedMeta = [];
            if ($order->dripfeed_enabled && $order->dripfeed_runs_total) {
                $dripfeedMeta = [
                    'enabled' => true,
                    'runs_total' => $order->dripfeed_runs_total,
                    'interval_minutes' => $order->dripfeed_interval_minutes ?? 60,
                    'per_run_qty' => (int) ceil($order->quantity / max(1, $order->dripfeed_runs_total)),
                    'run_index' => 0,
                    'delivered_in_run' => 0,
                    'next_run_at' => $order->dripfeed_next_run_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ];
            }

            // Calculate effective priority for queue routing
            $effectivePriority = $this->calculateEffectivePriority($order);

            // Update provider_payload with execution metadata
            $providerPayload['execution_meta'] = [
                'link_type' => $linkType,
                'action' => $action,
                'interval_seconds' => $intervalSeconds,
                'per_call' => $perCall,
                'steps_total' => $stepsTotal,
                'eta_seconds' => $etaSeconds,
                'eta_at' => $etaAt->toDateTimeString(),
                'next_run_at' => now()->toDateTimeString(),
                'dripfeed' => $dripfeedMeta,
                // Debug fields for interval calculation
                'qty_policy' => $intervalData['qty_policy'],
                'member_policy' => $intervalData['member_policy'],
                'speed_policy' => $intervalData['speed_policy'],
            ];

            $providerPayload['policy_snapshot'] = $policySnapshot;
            // Transition to awaiting (single update)
            $order->update([
                'status' => Order::STATUS_AWAITING,
                'start_count' => $memberCount,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
                'provider_sending_at' => null,
                'provider_payload' => $providerPayload,
            ]);

            Log::info('Telegram link inspection successful, execution metadata calculated', [
                'order_id' => $this->orderId,
                'chat_type' => $inspectionResult['chat_type'] ?? null,
                'title' => $inspectionResult['title'] ?? null,
                'service_type' => $serviceType,
                'link_type' => $linkType,
                'action' => $policy['action'],
                'steps_total' => $stepsTotal,
                'eta_at' => $etaAt->toDateTimeString(),
                'speed_tier' => $speedTier,
            ]);

            // Pull mode: no dispatch, tasks will be generated by telegram:tasks:generate
        } finally {
            Order::query()->whereKey($this->orderId)->update(['provider_sending_at' => null]);
        }
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);

        if ($order) {
            $order->update([
                'status' => Order::STATUS_INVALID_LINK,
                'provider_last_error' => $exception->getMessage(),
                'provider_last_error_at' => now(),
                'provider_sending_at' => null,
            ]);

            // ✅ retries exhausted => refund
            try {
                app(OrderService::class)->refundInvalid($order, $exception->getMessage());
            } catch (\Throwable $e) {
                Log::warning('Refund failed in InspectTelegramLinkJob::failed', [
                    'order_id' => $this->orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('InspectTelegramLinkJob failed', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function releaseClaim(Order $order): void
    {
        $order->update(['provider_sending_at' => null]);
    }

    private function validateAgainstTemplate(array $inspectionResult, array $template): array
    {
        $errors = [];
        $parsed = $inspectionResult['parsed'] ?? [];
        $linkKind = $parsed['kind'] ?? 'unknown';
        $chatType = $inspectionResult['chat_type'] ?? null;
        // Check allowed link kinds
        $allowedLinkKinds = $template['allowed_link_kinds'] ?? [];

        if (!empty($allowedLinkKinds) && !in_array($linkKind, $allowedLinkKinds, true)) {
            $errors[] = "Link kind '{$linkKind}' is not allowed for this service template. Allowed: " . implode(', ', $allowedLinkKinds);
        }

        // Check allowed peer types
        $allowedPeerTypes = $template['allowed_peer_types'] ?? [];
        if (!empty($allowedPeerTypes) && $chatType && !in_array($chatType, $allowedPeerTypes, true)) {
            // Special case: 'supergroup' should match 'group' templates
            if ($chatType === 'supergroup' && in_array('group', $allowedPeerTypes, true)) {
                // allow
            } elseif ($chatType === 'group' && in_array('supergroup', $allowedPeerTypes, true)) {
                // allow
            } else {
                $errors[] = "Chat type '{$chatType}' is not allowed for this service template. Allowed: " . implode(', ', $allowedPeerTypes);
            }
        }

        // Check for paid join (unsupported)
        if (!empty($inspectionResult['is_paid_join'])) {
            $errors[] = 'Paid Telegram groups are not supported';
        }

        if (!empty($template['requires_start_param'])) {
            $startParam = (string)($parsed['start'] ?? '');
            $hasRef = $linkKind === 'bot_start_with_referral' || $startParam !== '';

            if (!$hasRef) {
                $errors[] = 'This service requires a referral start parameter in the bot link';
            }
        }

        return $errors;
    }

    private function isRetryableInspectionError(string $errorCode): bool
    {
        $errorCode = strtoupper($errorCode);

        return in_array($errorCode, [
            'REQUEST_FAILED',          // network/timeouts from Bot API resolver
            'INVALID_RESPONSE',        // temporary upstream weirdness
            'UNKNOWN_ERROR',           // safest to retry a couple times
            'MTPROTO_ERROR',           // MTProto RPC/network can be flaky
            'MTPROTO_INIT_FAILED',     // sometimes file locks / fs issues
            'MTPROTO_NOT_AUTHORIZED',
        ], true);
    }

    /**
     * Calculate effective priority for queue routing.
     * Formula: base priority + speed tier boost - dripfeed penalty
     */
    private function calculateEffectivePriority(Order $order): int
    {
        // Base priority from service (fallback 50)
        $basePriority = (int) ($order->service->priority ?? 50);

        // Speed tier boost
        $speedTier = (string) ($order->speed_tier ?? 'normal');
        $speedBoost = match ($speedTier) {
            'fast' => 20,
            'super_fast' => 20,
            'normal' => 0,
            'slow' => -10,
            default => 0,
        };

        // Dripfeed penalty
        $dripfeedPenalty = $order->dripfeed_enabled ? -20 : 0;

        $effectivePriority = $basePriority + $speedBoost + $dripfeedPenalty;

        // Clamp to [0..120]
        return max(0, min(120, $effectivePriority));
    }

    /**
     * Get queue name based on effective priority.
     */
    private function getQueueForPriority(int $priority): string
    {
        if ($priority >= 90) {
            return 'tg-p0';
        } elseif ($priority >= 70) {
            return 'tg-p1';
        } elseif ($priority >= 40) {
            return 'tg-p2';
        } else {
            return 'tg-p3';
        }
    }

    /**
     * Calculate interval using qty tiers + member_count + speed_tier.
     * Returns array with interval_seconds, steps_total, eta_seconds, and debug fields.
     */
    private function calculateInterval(
        int $quantity,
        int $perCall,
        string $action,
        int $memberCount,
        string $speedTier,
        float $speedMultiplier,
        ?string $policyKey = null
    ): array {
        // Determine if heavy or light action
        $isHeavy = in_array($action, ['subscribe', 'unsubscribe'], true);
        $tierKey = $isHeavy ? 'heavy' : 'light';
        $qtyTiers = config("telegram.qty_target_eta.{$tierKey}", []);

        // Find target ETA based on quantity
        if ($quantity <= 1000) {
            $targetEtaSeconds = (int) ($qtyTiers['<=1000'] ?? 120);
        } elseif ($quantity <= 10000) {
            $targetEtaSeconds = (int) ($qtyTiers['<=10000'] ?? ($isHeavy ? 3600 : 1200));
        } elseif ($quantity <= 50000) {
            // NOTE: for light you probably want <=50000 key; if not present fallback to <=100000
            $targetEtaSeconds = (int) (
            $isHeavy
                ? ($qtyTiers['<=50000'] ?? 43200)
                : ($qtyTiers['<=50000'] ?? ($qtyTiers['<=100000'] ?? 28800))
            );
        } elseif ($quantity <= 100000) {
            $targetEtaSeconds = (int) (
            $isHeavy
                ? ($qtyTiers['<=100000'] ?? 345600)
                : ($qtyTiers['<=100000'] ?? 28800)
            );
        } else {
            $targetEtaSeconds = (int) ($qtyTiers['else'] ?? ($isHeavy ? 518400 : 86400));
        }

        // Calculate steps_total
        $stepsTotal = (int) ceil($quantity / max(1, $perCall));

        // Calculate interval_from_qty
        $intervalFromQty = max(1, (int) ceil($targetEtaSeconds / max(1, $stepsTotal)));

        // Apply member_count multiplier (only for subscribe/join and only for bigger orders)
        $applyMember = $this->shouldApplyMemberCount($action);

        $memberMultiplier = 1.0;
        if ($applyMember && $quantity > 1000) {
            // optionally: only slow down non-public policies
            if ($policyKey !== 'sub_public') {
                $memberMultiplier = $this->getMemberMultiplier($memberCount);
            }
        }

        $intervalWithMember = (int) ceil($intervalFromQty * $memberMultiplier);

        // Apply speed multiplier (faster = shorter interval)
        $intervalWithSpeed = max(1, (int) ceil($intervalWithMember / max(0.1, $speedMultiplier)));

        // Clamp by action type
        if ($isHeavy) {
            // default heavy min
            $minHeavy = 5;

            // public subscribe can be faster for small orders
            if ($policyKey === 'sub_public') {
                $minHeavy = ($quantity <= 1000) ? 1 : 5;
            }

            $intervalFinal = max($minHeavy, min(3600, $intervalWithSpeed));
        } else {
            // light actions
            $intervalFinal = max(1, min(900, $intervalWithSpeed));
        }

        // Calculate final ETA
        $etaSeconds = $stepsTotal * $intervalFinal;

        // Determine member tier for debug
        $memberTier = $this->getMemberTier($memberCount);

        return [
            'interval_seconds' => $intervalFinal,
            'steps_total' => $stepsTotal,
            'eta_seconds' => $etaSeconds,
            'qty_policy' => [
                'eta_target_seconds' => $targetEtaSeconds,
                'interval_from_qty' => $intervalFromQty,
            ],
            'member_policy' => [
                'member_count' => $memberCount,
                'tier' => $memberTier,
                'multiplier' => $memberMultiplier,
            ],
            'speed_policy' => [
                'speed_tier' => $speedTier,
                'speed_multiplier' => $speedMultiplier,
            ],
        ];
    }


    /**
     * Get member count multiplier from config.
     */
    private function getMemberMultiplier(int $memberCount): float
    {
        $multipliers = config('telegram.member_count_multipliers', []);

        if ($memberCount <= 0) {
            return (float) ($multipliers['unknown'] ?? 3.0);
        } elseif ($memberCount <= 100) {
            return (float) ($multipliers['tiny'] ?? 2.0);
        } elseif ($memberCount <= 1000) {
            return (float) ($multipliers['small'] ?? 1.4);
        } elseif ($memberCount <= 10000) {
            return (float) ($multipliers['medium'] ?? 1.0);
        } else {
            return (float) ($multipliers['large'] ?? 1.2);
        }
    }

    /**
     * Get member tier name for debug.
     */
    private function getMemberTier(int $memberCount): string
    {
        if ($memberCount <= 0) {
            return 'unknown';
        } elseif ($memberCount <= 100) {
            return 'tiny';
        } elseif ($memberCount <= 1000) {
            return 'small';
        } elseif ($memberCount <= 10000) {
            return 'medium';
        } else {
            return 'large';
        }
    }

    private function shouldApplyMemberCount(string $action): bool
    {
        return in_array($action, ['subscribe', 'join'], true);
    }

}
