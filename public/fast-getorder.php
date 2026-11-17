<?php

/**
 * Fast path for GET /api/provider/telegram/getOrder (and /premium/getOrder).
 *
 * ~99% of performer polls end with "no tasks": the poll is throttled, or the
 * service / phone has a no-work flag in Redis. Booting Laravel for those costs
 * ~6 CPU cores at ~800 req/s. This script repeats exactly the same cheap
 * checks, in the same order and on the same Redis keys, without booting the
 * framework:
 *
 *   1. provider token          (AuthenticateProvider)
 *   2. per account+service throttle (ThrottleProviderPolling)
 *   3. scope lookup from cache (TelegramTaskClaimController::resolveScope)
 *   4. PFADD stats + no-work flags (TelegramTaskClaimController::claimByScope)
 *
 * It only ever answers "no tasks". Anything else — a possible task, invalid
 * input, missing cache, Redis errors, maintenance mode — is handed to Laravel
 * unchanged, so task claiming and error responses stay in one place.
 *
 * Keep the key formats in sync with the classes above.
 */

const FASTPATH_EMPTY_RESPONSE = '{"ok":true,"count":0,"tasks":[]}';

function fastpath_empty(): never
{
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, private');
    header('X-Fastpath: 1');
    echo FASTPATH_EMPTY_RESPONSE;
    exit;
}

function fastpath_delegate(): never
{
    $_SERVER['SCRIPT_FILENAME'] = __DIR__.'/index.php';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';

    require __DIR__.'/index.php';
    exit;
}

function fastpath_input(string $key): mixed
{
    $value = $_GET[$key] ?? null;

    // Mirror Laravel's TrimStrings + ConvertEmptyStringsToNull.
    if (is_string($value)) {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
    || file_exists(__DIR__.'/../storage/framework/maintenance.php')
    || ! is_file($configPath = __DIR__.'/../bootstrap/cache/config.php')
    || ! class_exists(\Redis::class)) {
    fastpath_delegate();
}

$config = require $configPath;

// 1. Provider token — on any mismatch let Laravel produce the 401/500.
$expectedToken = $config['services']['provider']['token'] ?? null;
$providedToken = fastpath_input('api_token') ?? ($_SERVER['HTTP_API_TOKEN'] ?? null);

if (empty($expectedToken) || ! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
    fastpath_delegate();
}

// Validation failures are Laravel's job too.
$account = fastpath_input('account_identity');
$serviceIdRaw = fastpath_input('service_id');
$serviceId = is_string($serviceIdRaw) ? filter_var($serviceIdRaw, FILTER_VALIDATE_INT) : false;

if (! is_string($account) || $serviceId === false) {
    fastpath_delegate();
}

// Decide inside try, act outside it: Laravel must never run inside this catch.
$noTasks = false;

try {
    $redisConfig = $config['database']['redis']['default'];

    $redis = new \Redis;
    // Short timeouts: if Redis is down, fall through to Laravel quickly
    // instead of stalling the request twice.
    $redis->pconnect(
        $redisConfig['host'] ?? '127.0.0.1',
        (int) ($redisConfig['port'] ?? 6379),
        0.5,
        'fast-getorder'
    );

    if (! empty($redisConfig['password'])) {
        $redis->auth(! empty($redisConfig['username'])
            ? [$redisConfig['username'], $redisConfig['password']]
            : $redisConfig['password']);
    }

    // Persistent connection keeps its db — skip the extra round-trip.
    $database = (int) ($redisConfig['database'] ?? 0);
    if ($redis->getDbNum() !== $database) {
        $redis->select($database);
    }
    $redis->setOption(\Redis::OPT_PREFIX, (string) ($config['database']['redis']['options']['prefix'] ?? ''));
    $redis->setOption(\Redis::OPT_READ_TIMEOUT, 1);

    // 2. Throttle — same key as ThrottleProviderPolling: route segment(2) is
    // always "provider", and service_id is the raw (trimmed) input string.
    $interval = (int) ($config['services']['provider']['poll_interval_seconds'] ?? 10);
    $throttleKey = 'throttle:'.md5("poll:provider:{$account}:{$serviceIdRaw}");

    if (! $redis->set($throttleKey, 1, ['nx', 'ex' => $interval])) {
        $noTasks = true;
    } else {
        // Laravel must not acquire the throttle key a second time.
        define('PROVIDER_POLL_THROTTLE_ACQUIRED', true);

        // 3. Scope — only from cache; a miss means Laravel has to look it up.
        $scope = $redis->get("tg:svc_scope:{$serviceId}");

        if ($scope === 'default' || $scope === 'premium') {
            // 4. Stats + no-work flags.
            date_default_timezone_set($config['app']['timezone'] ?? 'UTC');
            $hourKey = "tg:claim_attempts:{$serviceId}:".date('Y-m-d-H');

            $results = $redis->multi(\Redis::PIPELINE)
                ->pfAdd($hourKey, [$account])
                ->expire($hourKey, 7200)
                ->exists("tg:no_work:{$scope}:{$serviceId}")
                ->exists("tg:no_work:phone:{$account}:{$scope}:{$serviceId}")
                ->exec();

            $noTasks = ! empty($results[2]) || ! empty($results[3]);
        }
    }
} catch (\Throwable) {
    // Redis unavailable — Laravel fails open the same way.
}

$noTasks ? fastpath_empty() : fastpath_delegate();
