<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\App\AppPageInspector;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class InspectAppLinkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;
    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public int $orderId) {}

    public function handle(AppPageInspector $inspector, OrderService $orderService): void
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
            Log::warning('Order not found after claim (App)', ['order_id' => $this->orderId]);
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
            if ($driver !== 'app') {
                $this->releaseClaim($order);
                return;
            }

            $link = trim((string) ($order->link ?? ''));
            if ($link === '') {
                $this->failOrder($order, 'App link is empty', $orderService);
                return;
            }

            $inspectionResult = $inspector->inspect($link);

            if (!($inspectionResult['ok'] ?? false)) {
                $errorMessage = $inspectionResult['error'] ?? 'App link validation failed';
                $this->failOrder($order, $errorMessage, $orderService, $inspectionResult);
                return;
            }

            $providerPayload = $order->provider_payload ?? [];
            if (!is_array($providerPayload)) {
                $providerPayload = [];
            }

            $appMeta = [
                'ok' => true,
                'platform' => $inspectionResult['platform'] ?? null,
                'identifier' => $inspectionResult['identifier'] ?? null,
                'normalized_url' => $inspectionResult['normalized_url'] ?? null,
                'target_hash' => $this->appTargetHash($inspectionResult),
                'downloads_visibility' => $inspectionResult['downloads_visibility'] ?? AppPageInspector::DOWNLOADS_VISIBILITY_UNAVAILABLE,
                'downloads_range_label' => $inspectionResult['downloads_range_label'] ?? null,
                'downloads_source' => $inspectionResult['downloads_source'] ?? AppPageInspector::DOWNLOADS_SOURCE_NONE,
                'downloads_last_checked_at' => now()->toIso8601String(),
            ];
            $providerPayload['app'] = $appMeta;

            $executionMeta = $this->buildExecutionMeta($order, $providerPayload['app']);
            $providerPayload['execution_meta'] = $executionMeta;

            $order->update([
                'status' => Order::STATUS_AWAITING,
                'provider_last_error' => null,
                'provider_last_error_at' => null,
                'provider_sending_at' => null,
                'provider_payload' => $providerPayload,
            ]);

            Log::info('App link inspection passed', [
                'order_id' => $order->id,
                'platform' => $appMeta['platform'],
                'identifier' => $appMeta['identifier'],
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
                Log::warning('Refund failed in InspectAppLinkJob::failed', [
                    'order_id' => $this->orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::error('InspectAppLinkJob failed', [
            'order_id' => $this->orderId,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function releaseClaim(Order $order): void
    {
        $order->update(['provider_sending_at' => null]);
    }

    private function appTargetHash(array $inspectionResult): string
    {
        $platform = $inspectionResult['platform'] ?? 'unknown';
        $identifier = $inspectionResult['identifier'] ?? '';
        return hash('sha256', 'app:' . $platform . ':' . $identifier);
    }

    private function buildExecutionMeta(Order $order, array $appMeta): array
    {
        $service = $order->service;
        $templateKey = $service->template_key ?? null;
        if (!$templateKey || !str_starts_with($templateKey, 'app_')) {
            return [
                'action' => 'download',
                'mode' => 'single',
                'steps' => ['download'],
                'per_call' => 1,
            ];
        }

        $template = config("app_service_templates.{$templateKey}");
        if (!is_array($template)) {
            return [
                'action' => 'download',
                'mode' => 'single',
                'steps' => ['download'],
                'per_call' => 1,
            ];
        }

        $mode = $template['mode'] ?? 'single';
        $steps = $template['steps'] ?? [$template['action'] ?? 'download'];
        $action = $template['action'] ?? $steps[0] ?? 'download';

        if (!is_array($steps)) {
            $steps = [$steps];
        }
        $steps = array_map('strtolower', $steps);

        return [
            'action' => $action,
            'mode' => $mode,
            'steps' => $steps,
            'per_call' => 1,
            'target_hash' => $appMeta['target_hash'] ?? null,
        ];
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
        $providerPayload['app'] = $inspectionResult ?? ['ok' => false, 'error' => $errorMessage];

        $order->update([
            'status' => Order::STATUS_INVALID_LINK,
            'provider_last_error' => $errorMessage,
            'provider_last_error_at' => now(),
            'provider_sending_at' => null,
            'provider_payload' => $providerPayload,
        ]);

        $orderService->refundInvalid($order, $errorMessage);

        Log::info('App link inspection failed', [
            'order_id' => $order->id,
            'error' => $errorMessage,
        ]);
    }
}
