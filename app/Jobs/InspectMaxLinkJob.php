<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Support\Links\Inspectors\MaxLinkInspector;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validates MAX Messenger order links, checks against service template rules,
 * calculates execution metadata, and transitions order to AWAITING.
 */
class InspectMaxLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId)
    {
        $this->onQueue('max-inspect');
    }

    public function handle(OrderService $orderService): void
    {
        // 1) Atomic claim with TTL
        $claimed = Order::query()
            ->whereKey($this->orderId)
            ->where('status', Order::STATUS_VALIDATING)
            ->where(function ($q) {
                $q->whereNull('provider_sending_at')
                    ->orWhere('provider_sending_at', '<', now()->subMinutes(5));
            })
            ->update(['provider_sending_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $order = Order::query()->with('service')->find($this->orderId);

        if (! $order || $order->status !== Order::STATUS_VALIDATING) {
            return;
        }

        try {
            if (! $order->service) {
                $this->failOrder($order, $orderService, 'Service not found for order');

                return;
            }

            $link = trim((string) $order->link);
            if ($link === '') {
                $this->failOrder($order, $orderService, 'Empty link');

                return;
            }

            // 2) Inspect the link
            $inspector = new MaxLinkInspector;
            $inspection = $inspector->inspect($link);

            if (! ($inspection['valid'] ?? false)) {
                $this->failOrder($order, $orderService, $inspection['error'] ?? 'Invalid MAX Messenger link');

                return;
            }

            $linkKind = $inspection['kind'] ?? 'unknown';
            $parsed = $inspection['parsed'] ?? [];

            // 2b) Check if invite/join link is expired
            if ($linkKind === 'max_invite') {
                try {
                    $response = Http::timeout(5)->get($link);
                    if (str_contains($response->body(), 'Перейти в Max')) {
                        $this->failOrder($order, $orderService, __('MAX Messenger invite link is expired or not working.'));

                        return;
                    }
                } catch (Throwable) {
                    // Network error — don't block, retry will handle it
                }
            }

            // 3) Load template and validate
            $template = $order->service->template();

            if ($template) {
                $errors = $this->validateAgainstTemplate($inspection, $template);

                if (! empty($errors)) {
                    $this->failOrder($order, $orderService, implode('; ', $errors));

                    return;
                }
            }

            // 4) Calculate execution metadata
            $action = (string) ($template['action'] ?? 'subscribe');
            $isHeavy = in_array($action, ['subscribe', 'unsubscribe'], true);
            $speedTier = (string) ($order->speed_tier ?? 'normal');
            $speedMultiplier = $order->service->getSpeedMultiplier($speedTier);

            $perCall = 1;
            $baseInterval = $this->calculateBaseInterval($order->quantity, $isHeavy);
            $intervalSeconds = (int) max(1, round($baseInterval / max(0.1, $speedMultiplier)));

            $stepsTotal = (int) ceil($order->quantity / max(1, $perCall));
            $etaSeconds = max(0, $stepsTotal - 1) * $intervalSeconds;

            // 5) Build provider_payload
            $providerPayload = $order->provider_payload ?? [];
            if (! is_array($providerPayload)) {
                $providerPayload = [];
            }

            $providerPayload['max'] = [
                'ok' => true,
                'kind' => $linkKind,
                'parsed' => $parsed,
            ];

            $providerPayload['execution_meta'] = [
                'link_type' => $linkKind,
                'action' => $action,
                'interval_seconds' => $intervalSeconds,
                'per_call' => $perCall,
                'steps_total' => $stepsTotal,
                'eta_seconds' => $etaSeconds,
                'eta_at' => now()->addSeconds($etaSeconds)->toDateTimeString(),
                'next_run_at' => now()->toDateTimeString(),
                'speed_tier' => $speedTier,
                'speed_multiplier' => $speedMultiplier,
                'template_key' => $order->service->template_key ?? null,
            ];

            // Dripfeed metadata
            if ($order->dripfeed_enabled && $order->dripfeed_runs_total) {
                $providerPayload['execution_meta']['dripfeed'] = [
                    'enabled' => true,
                    'runs_total' => $order->dripfeed_runs_total,
                    'interval_minutes' => $order->dripfeed_interval_minutes ?? 60,
                    'per_run_qty' => (int) ceil($order->quantity / max(1, $order->dripfeed_runs_total)),
                ];
            }

            // 6) Transition to awaiting
            $order->update([
                'status' => Order::STATUS_AWAITING,
                'execution_phase' => Order::EXECUTION_PHASE_RUNNING,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
                'provider_sending_at' => null,
                'provider_payload' => $providerPayload,
            ]);
        } catch (Throwable $e) {
            // Release claim so retry can pick it up
            $order->update(['provider_sending_at' => null]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $order = Order::query()->find($this->orderId);
        if (! $order) {
            return;
        }

        $order->update([
            'status' => Order::STATUS_INVALID_LINK,
            'provider_last_error' => $exception->getMessage(),
            'provider_last_error_at' => now(),
            'provider_sending_at' => null,
        ]);

        try {
            app(OrderService::class)->refundInvalid($order, $exception->getMessage());
        } catch (Throwable $e) {
            Log::warning('InspectMaxLinkJob refund failed', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage(),
            ]);
        }

        Log::error('InspectMaxLinkJob failed', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function failOrder(Order $order, OrderService $orderService, string $message): void
    {
        $providerPayload = $order->provider_payload ?? [];
        if (! is_array($providerPayload)) {
            $providerPayload = [];
        }

        $providerPayload['max'] = [
            'ok' => false,
            'error' => $message,
        ];

        $order->update([
            'status' => Order::STATUS_INVALID_LINK,
            'provider_last_error' => $message,
            'provider_last_error_at' => now(),
            'provider_sending_at' => null,
            'provider_payload' => $providerPayload,
        ]);

        $orderService->refundInvalid($order, $message);

        Log::info('InspectMaxLinkJob: order invalid', [
            'order_id' => $this->orderId,
            'error' => $message,
        ]);
    }

    private function validateAgainstTemplate(array $inspection, array $template): array
    {
        $errors = [];
        $parsed = $inspection['parsed'] ?? [];
        $linkKind = $parsed['kind'] ?? ($inspection['kind'] ?? 'unknown');

        // Check allowed link kinds
        $allowedKinds = $template['allowed_link_kinds'] ?? [];
        if (! empty($allowedKinds) && ! in_array($linkKind, $allowedKinds, true)) {
            // max_channel can also be a bot — allow if template accepts max_bot
            if (! ($linkKind === 'max_channel' && in_array('max_bot', $allowedKinds, true))) {
                $errors[] = "Link kind '".self::humanKind($linkKind)."' is not allowed. Allowed: ".implode(', ', array_map(self::humanKind(...), $allowedKinds));
            }
        }

        // Check start param for bot referral
        if (! empty($template['requires_start_param'])) {
            $startParam = (string) ($parsed['start'] ?? '');
            $hasRef = $linkKind === 'max_bot_with_referral' || $startParam !== '';

            if (! $hasRef) {
                $errors[] = 'This service requires a referral start parameter in the bot link';
            }
        }

        return $errors;
    }

    private static function humanKind(string $kind): string
    {
        return match ($kind) {
            'max_bot' => 'bot',
            'max_bot_with_referral' => 'bot with referral',
            'max_channel' => 'channel',
            'max_post' => 'post',
            'max_invite' => 'invite',
            'max_user_profile' => 'user profile',
            default => str_replace('max_', '', $kind),
        };
    }

    /**
     * Base interval in seconds based on quantity. Simple tiers.
     */
    private function calculateBaseInterval(int $quantity, bool $isHeavy): int
    {
        if ($isHeavy) {
            return match (true) {
                $quantity <= 100 => 5,
                $quantity <= 1000 => 15,
                $quantity <= 10000 => 30,
                default => 60,
            };
        }

        // Light actions (view, react, repost)
        return match (true) {
            $quantity <= 1000 => 2,
            $quantity <= 10000 => 5,
            default => 10,
        };
    }
}
