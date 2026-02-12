<?php

namespace App\Services\Telegram;

use App\Models\Order;
use App\Services\Provider\ProviderClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Centralized service for handling Telegram step completion.
 *
 * This service consolidates completion logic for:
 * - Synchronous provider responses (done/failed)
 * - Webhook callbacks
 * - Polling fallback results
 *
 * Responsibilities:
 * - Publish step results to Redis stream
 * - Update order metadata and steps log
 * - Apply dripfeed counters and advance logic
 * - Schedule unsubscribe if subscribe succeeded
 */
class TelegramStepCompletionService
{
    public function __construct(
        private ProviderClient $providerClient,
    ) {}

    /**
     * Handle step completion.
     *
     * @param Order $order
     * @param int $accountId
     * @param string $action
     * @param string $linkHash
     * @param array $result Normalized provider result: {state, ok, error, retry_after, task_id, raw}
     * @param array $parsed Parsed telegram link data
     * @return void
     */
    public function handle(
        Order $order,
        int $accountId,
        string $action,
        string $linkHash,
        array $result,
        array $parsed,
    ): void {
        $state = $result['state'] ?? 'done';
        $ok = (bool) ($result['ok'] ?? false);
        $error = $result['error'] ?? null;
        $retryAfter = $result['retry_after'] ?? null;

        $providerPayload = $order->provider_payload ?? [];
        if (!is_array($providerPayload)) {
            $providerPayload = [];
        }

        $executionMeta = $providerPayload['execution_meta'] ?? [];
        if (!is_array($executionMeta)) {
            $executionMeta = [];
        }

        $perCall = (int) ($executionMeta['per_call'] ?? 1);
        $intervalSeconds = (int) ($executionMeta['interval_seconds'] ?? 60);

        // Publish step result to Redis stream
        $this->providerClient->publishStepResult(
            Order::class,
            $order->id,
            $accountId,
            $action,
            $linkHash,
            $ok,
            $error,
            $perCall,
            $retryAfter,
            [
                'order_id' => $order->id,
                'task_id' => $result['task_id'] ?? null,
            ]
        );

        // Update steps debug log (keep last 100)
        $steps = $providerPayload['steps'] ?? [];
        if (!is_array($steps)) {
            $steps = [];
        }
        if (count($steps) >= 100) {
            $steps = array_slice($steps, -99);
        }

        $steps[] = [
            'at' => now()->toDateTimeString(),
            'account_id' => $accountId,
            'ok' => $ok,
            'error' => $error,
            'task_id' => $result['task_id'] ?? null,
            'state' => $state,
        ];
        $providerPayload['steps'] = $steps;

        // Update next_run_at based on retry_after or interval
        $nextDelay = $this->nextDelaySeconds($intervalSeconds, $retryAfter);
        $executionMeta['next_run_at'] = now()->addSeconds($nextDelay)->toDateTimeString();

        // Apply dripfeed logic if enabled
        if ($order->dripfeed_enabled && isset($executionMeta['dripfeed'])) {
            $this->applyDripfeedCompletion($order, $ok, $perCall, $executionMeta);
        }

        // Clear provider task tracking
        $executionMeta['provider_task_id'] = null;
        $executionMeta['provider_task_state'] = null;
        $providerPayload['execution_meta'] = $executionMeta;

        // Update order status and error (always update these)
        $order->update([
            'provider_last_error' => $ok ? null : ($error ?? 'Unknown error'),
            'provider_last_error_at' => $ok ? null : now(),
            'status' => $ok ? Order::STATUS_IN_PROGRESS : Order::STATUS_PENDING,
            'provider_payload' => $providerPayload,
        ]);



        if ($ok) {
            $processed = max(1, $perCall);
            Order::whereKey($order->id)->update([
                'delivered' => DB::raw("delivered + {$processed}"),
                'remains'   => DB::raw("
                CASE
                    WHEN remains - {$processed} < 0 THEN 0
                    ELSE remains - {$processed}
                END
                "),
                'status' => Order::STATUS_IN_PROGRESS,
            ]);
            $order->refresh();
        }

        // Commit state after provider success (subscribe/unsubscribe only)
        if ($ok && in_array($action, ['subscribe', 'unsubscribe'], true)) {
            $claimService = app(TelegramAccountClaimService::class);
            $claimService->commitState($accountId, $action, $linkHash);
        }



        Log::info('Telegram step completed', [
            'order_id' => $order->id,
            'account_id' => $accountId,
            'action' => $action,
            'ok' => $ok,
            'per_call' => $perCall,
            'next_delay' => $nextDelay,
        ]);

        // Dispatch next step only in push mode (legacy)
        // In pull mode: tasks are generated by telegram:tasks:generate command
        $usePullProvider = config('telegram.use_pull_provider', true);

        // Pull mode: no dispatch, tasks will be generated by telegram:tasks:generate

        // Mark order as completed if no remains left
        if ($ok && (int) $order->remains <= 0) {
            $order->update(['status' => Order::STATUS_COMPLETED, 'remains' => 0]);
        }
    }

    /**
     * Apply dripfeed completion logic.
     *
     * @param Order $order
     * @param bool $ok
     * @param int $perCall
     * @param array $executionMeta (passed by reference)
     * @return void
     */
    private function applyDripfeedCompletion(Order $order, bool $ok, int $perCall, array &$executionMeta): void
    {
        $dripfeed = $executionMeta['dripfeed'] ?? [];
        if (empty($dripfeed['enabled'])) {
            return;
        }

        $runIndex = (int) ($dripfeed['run_index'] ?? 0);
        $deliveredInRun = (int) ($dripfeed['delivered_in_run'] ?? 0);
        $perRunQty = (int) ($dripfeed['per_run_qty'] ?? 0);
        $runsTotal = (int) ($dripfeed['runs_total'] ?? 0);
        $intervalMinutes = (int) ($dripfeed['interval_minutes'] ?? 60);

        if ($ok) {
            // Increment delivered in current run
            $deliveredInRun += $perCall;
            $dripfeed['delivered_in_run'] = $deliveredInRun;

            // Check if run is complete
            if ($deliveredInRun >= $perRunQty) {
                // Advance to next run
                $runIndex++;
                $dripfeed['run_index'] = $runIndex;
                $dripfeed['delivered_in_run'] = 0;
                $dripfeed['next_run_at'] = now()->addMinutes($intervalMinutes)->toDateTimeString();

                // Update order columns
                $order->update([
                    'dripfeed_run_index' => $runIndex,
                    'dripfeed_delivered_in_run' => 0,
                    'dripfeed_next_run_at' => now()->addMinutes($intervalMinutes),
                ]);

                // If all runs complete, disable dripfeed gating
                if ($runIndex >= $runsTotal) {
                    $dripfeed['enabled'] = false;
                }
            } else {
                // Update delivered count
                $order->update([
                    'dripfeed_delivered_in_run' => $deliveredInRun,
                ]);
            }
        }

        $executionMeta['dripfeed'] = $dripfeed;
    }

    /**
     * Calculate next delay seconds.
     *
     * @param int $intervalSeconds
     * @param int|null $retryAfter
     * @return int
     */
    private function nextDelaySeconds(int $intervalSeconds, ?int $retryAfter): int
    {
        $default = max(1, $intervalSeconds);

        if (is_numeric($retryAfter)) {
            $ra = (int) $retryAfter;
            return max(1, min($ra, 86400));
        }

        return $default;
    }
}
