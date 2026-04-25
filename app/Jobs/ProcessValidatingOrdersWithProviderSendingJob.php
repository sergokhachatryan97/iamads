<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Order\OrderInspectionDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sweeper for Orders stuck in VALIDATING status with a recorded error.
 *
 * Runs on a schedule (see bootstrap/app.php). Finds Orders that:
 *   - are still in VALIDATING,
 *   - have a recorded provider_last_error (i.e. the last inspection failed),
 *   - are NOT currently being processed (provider_sending_at is null OR
 *     older than the claim TTL),
 *   - have not been re-attempted within a minimum cool-down.
 *
 * For each match, the order is re-dispatched through
 * OrderInspectionDispatcher, which routes to the correct per-driver queue
 * (tg-panel-inspect for Telegram). This is the safety net that gets orders
 * unstuck when InspectTelegramLinkJob exhausts all its in-process retries
 * (5 × backoff) on a temporary MTProto failure.
 */
class ProcessValidatingOrdersWithProviderSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    /** Minimum time since last error before re-dispatching. */
    private const COOLDOWN_MINUTES = 5;

    /** Treat a claim older than this as stuck and re-dispatchable. */
    private const CLAIM_TTL_MINUTES = 10;

    /** Orders with no error older than this are considered stuck (never inspected). */
    private const STALE_NO_ERROR_MINUTES = 10;

    /** Max orders re-dispatched per run — prevents flooding tg-panel-inspect. */
    private const MAX_PER_RUN = 200;

    public function handle(OrderInspectionDispatcher $dispatcher): void
    {
        $cooldownCutoff = now()->subMinutes(self::COOLDOWN_MINUTES);
        $claimCutoff    = now()->subMinutes(self::CLAIM_TTL_MINUTES);
        $staleCutoff    = now()->subMinutes(self::STALE_NO_ERROR_MINUTES);

        $orders = Order::query()
            ->where('status', Order::STATUS_VALIDATING)
            ->where(function ($q) use ($cooldownCutoff, $claimCutoff, $staleCutoff) {
                // Case 1: Has a recorded error and cooldown has passed
                $q->where(function ($q2) use ($cooldownCutoff, $claimCutoff) {
                    $q2->whereNotNull('provider_last_error_at')
                        ->where('provider_last_error_at', '<', $cooldownCutoff)
                        ->where(function ($q3) use ($claimCutoff) {
                            $q3->whereNull('provider_sending_at')
                                ->orWhere('provider_sending_at', '<', $claimCutoff);
                        });
                })
                // Case 2: No error recorded, not claimed, and order is old enough to be considered stuck
                ->orWhere(function ($q2) use ($staleCutoff, $claimCutoff) {
                    $q2->whereNull('provider_last_error_at')
                        ->where(function ($q3) use ($claimCutoff) {
                            $q3->whereNull('provider_sending_at')
                                ->orWhere('provider_sending_at', '<', $claimCutoff);
                        })
                        ->where('created_at', '<', $staleCutoff);
                });
            })
            ->orderByRaw('provider_last_error_at IS NULL, provider_last_error_at ASC')
            ->limit(self::MAX_PER_RUN)
            ->get(['id']);

        if ($orders->isEmpty()) {
            return;
        }

        foreach ($orders as $order) {
            try {
                $full = Order::query()->with('service.category')->find($order->id);
                if (! $full || $full->status !== Order::STATUS_VALIDATING) {
                    continue;
                }

                $dispatcher->dispatch($full);
            } catch (\Throwable $e) {
                Log::warning('ProcessValidatingOrdersWithProviderSendingJob: dispatch failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ProcessValidatingOrdersWithProviderSendingJob: re-dispatched stuck validating orders', [
            'count' => $orders->count(),
        ]);
    }
}
