<?php

/*
|--------------------------------------------------------------------------
| System Guard (circuit breaker + kill switch)
|--------------------------------------------------------------------------
|
| Centralised knobs for the SystemGuard helper (App\Support\SystemGuard).
| These settings throttle heavy background work (provider polling, MTProto
| inspections, job dispatch) when the server is overloaded or paused by an
| operator. See App\Support\SystemGuard for usage.
|
| All values can be overridden via .env without a code deploy.
|
*/

return [

    /*
    |----------------------------------------------------------------------
    | Kill switch
    |----------------------------------------------------------------------
    | When true, all guard-protected jobs/pollers no-op. Use this during an
    | incident (database repair, provider outage, etc.) to stop generating
    | new work without stopping Horizon itself.
    |
    | The `system:pause` / `system:resume` artisan commands set a cache flag
    | that overrides this value at runtime — preferred so you don't need to
    | deploy to pause.
    */
    'pause' => env('SYSTEM_PAUSE', false),

    /*
    |----------------------------------------------------------------------
    | Load-average circuit breaker
    |----------------------------------------------------------------------
    | When the 1-minute load average exceeds this threshold, guard-protected
    | work is skipped. Pick a value ~1.5-2x the core count so normal bursts
    | don't trip it, but runaway work does.
    |
    | On a 16-core box, 20.0 = ~1.25x cores = alerts without stopping normal
    | peak traffic. Raise for more headroom, lower for stricter protection.
    */
    'load_threshold' => (float) env('SYSTEM_GUARD_LOAD_THRESHOLD', 20.0),

    /*
    |----------------------------------------------------------------------
    | Per-provider polling interval (seconds)
    |----------------------------------------------------------------------
    | Minimum time between two polls for the same provider+account. Pollers
    | use SystemGuard::claim() with this TTL to self-throttle. Jitter is
    | added on top so concurrent workers don't synchronise.
    */
    'provider_poll_interval_seconds' => (int) env('PROVIDER_POLL_INTERVAL_SECONDS', 8),

    /*
    |----------------------------------------------------------------------
    | Polling jitter (seconds, max)
    |----------------------------------------------------------------------
    | Maximum random delay added before a poll fires. Prevents thundering
    | herd when the scheduler dispatches multiple pollers at the same tick.
    */
    'poll_jitter_seconds' => (int) env('POLL_JITTER_SECONDS', 30),

    /*
    |----------------------------------------------------------------------
    | Max validate dispatches per poll run
    |----------------------------------------------------------------------
    | Hard cap on how many validate-inspection jobs a single poll run can
    | enqueue. Prevents one hot service with thousands of validating orders
    | from flooding the tg-inspect queue.
    |
    | Must stay small relative to the tg-inspect worker count so the queue
    | can drain between poll runs (~3 min apart).
    */
    'max_validate_dispatch_per_run' => (int) env('MAX_VALIDATE_DISPATCH_PER_RUN', 50),
];
