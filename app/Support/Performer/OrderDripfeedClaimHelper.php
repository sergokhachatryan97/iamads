<?php

namespace App\Support\Performer;

use App\Models\Order;
use Carbon\Carbon;

/**
 * Dripfeed gating and post-claim counters aligned with Telegram claim flow (order row fields).
 */
class OrderDripfeedClaimHelper
{
    /**
     * If dripfeed blocks claiming now, may update order (advance run / next_run_at) and returns false.
     */
    public static function canClaimTaskNow(Order $order): bool
    {
        if (!(bool) ($order->dripfeed_enabled ?? false)) {
            return true;
        }

        $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);
        $runIndex = (int) ($order->dripfeed_run_index ?? 0);
        $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
        $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);

        if ($runsTotal > 0 && $runIndex >= $runsTotal) {
            return false;
        }

        if (!empty($order->dripfeed_next_run_at)) {
            try {
                if (Carbon::parse($order->dripfeed_next_run_at)->isFuture()) {
                    return false;
                }
            } catch (\Throwable) {
                // treat as due
            }
        }

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
            return false;
        }

        return true;
    }

    /**
     * After a task is claimed under dripfeed: increment run counter and optionally schedule next run.
     */
    public static function afterTaskClaimed(Order $order): void
    {
        if (!(bool) ($order->dripfeed_enabled ?? false)) {
            return;
        }

        $perRunQty = (int) ($order->dripfeed_quantity ?? 0);
        $deliveredInRun = (int) ($order->dripfeed_delivered_in_run ?? 0);
        $runIndex = (int) ($order->dripfeed_run_index ?? 0);
        $runsTotal = (int) ($order->dripfeed_runs_total ?? 0);

        $deliveredInRun++;
        $updates = ['dripfeed_delivered_in_run' => $deliveredInRun];

        if ($perRunQty > 0 && $deliveredInRun >= $perRunQty) {
            $intervalMinutes = (int) ($order->dripfeed_interval_minutes ?? 0);
            if ($intervalMinutes <= 0) {
                $intervalMinutes = 60;
            }
            $updates['dripfeed_run_index'] = $runIndex + 1;
            $updates['dripfeed_delivered_in_run'] = 0;
            $updates['dripfeed_next_run_at'] = now()->addMinutes($intervalMinutes)->toDateTimeString();
            if ($runsTotal > 0 && ($runIndex + 1) >= $runsTotal) {
                $updates['dripfeed_enabled'] = false;
            }
        }

        $order->update($updates);
    }

    /**
     * On task failure after claim: roll back one dripfeed unit in current run (Telegram-style).
     */
    public static function rollbackClaimedUnit(Order $order): void
    {
        if (!(bool) ($order->dripfeed_enabled ?? false)) {
            return;
        }
        $order->update([
            'dripfeed_delivered_in_run' => max(0, (int) ($order->dripfeed_delivered_in_run ?? 0) - 1),
        ]);
    }
}
