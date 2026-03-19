<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\YouTube\YouTubeInspector;
use App\Services\YouTube\YouTubePolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InspectYouTubeLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId) {}

    public function handle(YouTubeInspector $inspector, OrderService $orderService): void
    {
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
            return;
        }

        $order = Order::query()->with(['service', 'service.category'])->find($this->orderId);

        if (!$order) {
            Log::warning('Order not found after claim (YouTube)', ['order_id' => $this->orderId]);
            return;
        }

        try {
            if ($order->status !== Order::STATUS_VALIDATING) {
                $this->releaseClaim($order);
                return;
            }

            if (!$order->service) {
                $this->failOrder($order, 'Service not found for order', $orderService);
                return;
            }

            $category = $order->service->category;
            $driver = $category->link_driver ?? 'generic';
            if ($driver !== 'youtube') {
                $this->releaseClaim($order);
                return;
            }

            $templateKey = $order->service->template_key ?? null;
            $template = $templateKey ? config("youtube_service_templates.{$templateKey}") : null;

            if (!$template) {
                $this->failOrder(
                    $order,
                    'YouTube service must have a valid template (e.g. yt_subscribe, yt_view, yt_react, yt_comment, yt_share, yt_watch_time, yt_live_view, yt_live_react, yt_live_comment_react, yt_live_comment)',
                    $orderService
                );
                return;
            }

            $link = trim((string) ($order->link ?? ''));
            if ($link === '') {
                $this->failOrder($order, 'YouTube link is empty', $orderService);
                return;
            }

            $inspectionResult = $inspector->inspect($link);

            if (!($inspectionResult['ok'] ?? false)) {
                $errorMessage = $inspectionResult['error'] ?? 'YouTube link validation failed';
                $this->failOrder($order, $errorMessage, $orderService, $inspectionResult);
                return;
            }

            $detailedLinkType = $inspectionResult['link_type'] ?? 'regular_video';
            $targetType = YouTubeInspector::linkTypeToTargetType($detailedLinkType);
            $allowedTargetTypes = $template['allowed_link_kinds'] ?? [];
            if (!in_array($targetType, $allowedTargetTypes, true)) {
                $this->failOrder(
                    $order,
                    "This link is a {$targetType} target. This service only allows: " . implode(', ', $allowedTargetTypes),
                    $orderService,
                    $inspectionResult
                );
                return;
            }

            $action = (string) ($template['action'] ?? 'view');
            $policyError = YouTubePolicy::validateActionForTargetType($action, $targetType);
            if ($policyError !== null) {
                $this->failOrder($order, $policyError, $orderService, $inspectionResult);
                return;
            }

            $actionDefaults = config('youtube.action_defaults.' . $action, []);
            $perCall = (int) ($actionDefaults['per_call'] ?? config('youtube.default_per_call', 1));
            $intervalSeconds = (int) ($actionDefaults['interval_seconds'] ?? config('youtube.default_interval_seconds', 30));

            $providerPayload = $order->provider_payload ?? [];
            if (!is_array($providerPayload)) {
                $providerPayload = [];
            }

            $inspectionResult['target_type'] = $targetType;
            $providerPayload['youtube'] = $inspectionResult;
            $channelId = $inspectionResult['channel_id'] ?? $inspectionResult['parsed']['channel_id'] ?? null;
            if ($channelId !== null) {
                $providerPayload['youtube']['channel_id'] = $channelId;
            }

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

            $executionMeta = [
                'link_type' => $detailedLinkType,
                'target_type' => $targetType,
                'action' => $action,
                'per_call' => $perCall,
                'interval_seconds' => $intervalSeconds,
                'next_run_at' => now()->toDateTimeString(),
                'dripfeed' => $dripfeedMeta,
            ];
            if ($channelId !== null) {
                $executionMeta['youtube_channel_id'] = $channelId;
            }
            if ($action === 'watch') {
                $service = $order->service;
                $prePayload = $order->provider_payload ?? [];
                $fromOrder = isset($prePayload['watch_time_seconds']) ? (int) $prePayload['watch_time_seconds'] : 0;
                $executionMeta['watch_time_seconds'] = $fromOrder > 0
                    ? $fromOrder
                    : (int) (
                        $service->watch_time_seconds
                        ?? $template['default_watch_time_seconds']
                        ?? config('youtube.default_watch_time_seconds', 30)
                    );
                if ($executionMeta['watch_time_seconds'] < 1) {
                    $executionMeta['watch_time_seconds'] = (int) ($template['default_watch_time_seconds'] ?? config('youtube.default_watch_time_seconds', 30));
                }
            }
            $providerPayload['execution_meta'] = $executionMeta;

            $order->update([
                'status' => Order::STATUS_AWAITING,
                'execution_phase' => Order::EXECUTION_PHASE_RUNNING,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
                'provider_sending_at' => null,
                'provider_payload' => $providerPayload,
            ]);

            Log::info('YouTube link inspection passed', [
                'order_id' => $order->id,
                'target_type' => $targetType,
                'action' => $action,
            ]);
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

            try {
                app(OrderService::class)->refundInvalid($order, $exception->getMessage());
            } catch (\Throwable $e) {
                Log::warning('Refund failed in InspectYouTubeLinkJob::failed', [
                    'order_id' => $this->orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('InspectYouTubeLinkJob failed', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function releaseClaim(Order $order): void
    {
        $order->update(['provider_sending_at' => null]);
    }

    private function failOrder(
        Order $order,
        string $errorMessage,
        OrderService $orderService,
        ?array $inspectionResult = null
    ): void {
        $providerPayload = $order->provider_payload ?? [];
        if (!is_array($providerPayload)) {
            $providerPayload = [];
        }
        $providerPayload['youtube'] = $inspectionResult ?? ['ok' => false, 'error' => $errorMessage];

        $order->update([
            'status' => Order::STATUS_INVALID_LINK,
            'provider_last_error' => $errorMessage,
            'provider_last_error_at' => now(),
            'provider_sending_at' => null,
            'provider_payload' => $providerPayload,
        ]);

        $orderService->refundInvalid($order, $errorMessage);

        Log::info('YouTube link inspection failed', [
            'order_id' => $order->id,
            'error' => $errorMessage,
        ]);
    }
}
