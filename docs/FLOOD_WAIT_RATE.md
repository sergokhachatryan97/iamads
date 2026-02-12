# Avoiding Telegram FLOOD_WAIT with Dynamic Rate

## Formula (review)

Telegram returns `FLOOD_WAIT_X` when you exceed their rate limit; `X` is the number of seconds you must wait before retrying.

**Proposed approach:**

1. Make **N** method calls until you get `FLOOD_WAIT_X`.
2. Let **T** = time (seconds) it took to make those N calls.
3. **floodwaitrate** = T + X (the “window” in which you did too much).
4. To avoid hitting the limit again: allow at most **N calls per floodwaitrate seconds**.

So the **minimum interval between calls** is:

- **interval** = floodwaitrate / N = (T + X) / N seconds per call.

Use `sleep` (or throttle) so you never do more than N calls in floodwaitrate seconds; equivalently, space calls by at least `(T + X) / N` seconds.

**Verdict: OK.** This is a reasonable heuristic:

- **T + X** approximates the effective “window” Telegram is enforcing: you made N calls in T seconds and were told to wait X more, so the total period is treated as T + X.
- **N / (T + X)** is then a safe “calls per second” rate; inverting gives the spacing between calls.
- Telegram’s exact limits are not public (and can vary by method), so this gives a practical, conservative rate that should reduce repeated FLOOD_WAITs.

**Caveats:**

- **N** and **T** depend on when you hit FLOOD_WAIT (could be 1st call or 100th). The formula is applied after the first FLOOD_WAIT to learn the rate.
- Limits can differ **per method**; using one rate per proxy (or per account) is a simplification but usually sufficient.
- Stored rate can be given a TTL (e.g. 1 hour) and a min/max interval so it doesn’t get stuck at an extreme value.

## Implementation in this codebase

- **Per-proxy call window:** Before each MTProto call we increment a “call count” for that proxy and record the window start time (first call in the window).
- **On FLOOD_WAIT_X:** We have N (count) and T (now − window start). We compute **interval = (T + X) / N**, clamp it to a min/max (e.g. 1–60 s), and store it per proxy (e.g. in cache with TTL).
- **Throttle:** In `waitForProxyThrottle` we use the stored interval when present (otherwise the configured default). We ensure at least `interval` seconds between calls for that proxy.

This way we **learn** the safe rate from the first FLOOD_WAIT and then throttle to stay under it.
