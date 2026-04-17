<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Redis-backed registry tracking all active MadelineProto IPC sessions.
 *
 * Enforces a hard global cap on concurrent sessions. When a new session is
 * requested and the cap is reached, the least-recently-used session is evicted
 * (its IPC worker killed). This prevents unbounded memory growth from
 * long-lived MadelineProto workers.
 *
 * Data model (Redis hash per session):
 *   mtp:session:{sessionName} → {pid, ipc_pid, account_id, started_at, last_used_at, memory_mb}
 *   mtp:sessions (sorted set) → score=last_used_at, member=sessionName
 */
class MtprotoSessionRegistry
{
    /** Maximum concurrent MadelineProto sessions. */
    private const MAX_SESSIONS = 15;

    /** Kill workers idle longer than this (seconds since last use). */
    private const MAX_IDLE_SECONDS = 900; // 15 minutes

    /** Kill workers older than this (seconds since start) regardless of activity. */
    private const MAX_LIFETIME_SECONDS = 1800; // 30 minutes

    /** Redis key for the sorted set of active sessions. */
    private const SESSIONS_KEY = 'mtp:sessions';

    /** Redis key prefix for per-session metadata. */
    private const SESSION_PREFIX = 'mtp:session:';

    /**
     * Try to acquire a slot for a session. Returns true if allowed to proceed.
     * If at cap, evicts the oldest session first.
     */
    public function acquire(string $sessionName, int $accountId): bool
    {
        try {
            // Already registered — just touch timestamp
            if (Redis::exists(self::SESSION_PREFIX . $sessionName)) {
                $this->touch($sessionName);
                return true;
            }

            // Check current count
            $count = (int) Redis::zcard(self::SESSIONS_KEY);

            if ($count >= self::MAX_SESSIONS) {
                // Do NOT evict active sessions — return false and let the caller retry.
                // The reaper (mtproto:reap) will free slots by killing stale/orphaned workers.
                Log::info('MtprotoSessionRegistry: at cap, no slot available', [
                    'current_count' => $count,
                    'max' => self::MAX_SESSIONS,
                    'requested' => $sessionName,
                ]);
                return false;
            }

            // Register the session
            $now = time();
            Redis::pipeline(function ($pipe) use ($sessionName, $accountId, $now) {
                $pipe->zadd(self::SESSIONS_KEY, $now, $sessionName);
                $pipe->hmset(self::SESSION_PREFIX . $sessionName, [
                    'account_id' => $accountId,
                    'started_at' => $now,
                    'last_used_at' => $now,
                    'pid' => 0,
                    'ipc_pid' => 0,
                ]);
                // Auto-expire metadata after 1 hour as safety net
                $pipe->expire(self::SESSION_PREFIX . $sessionName, 3600);
            });

            Log::info('MtprotoSessionRegistry: acquired', [
                'session' => $sessionName,
                'account_id' => $accountId,
                'active_sessions' => $count + 1,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('MtprotoSessionRegistry: acquire error', [
                'session' => $sessionName,
                'error' => $e->getMessage(),
            ]);
            // Fail open — allow the session
            return true;
        }
    }

    /**
     * Record the PIDs of the MadelineProto worker and its IPC child.
     */
    public function registerPids(string $sessionName, int $workerPid, int $ipcPid = 0): void
    {
        try {
            Redis::hmset(self::SESSION_PREFIX . $sessionName, [
                'pid' => $workerPid,
                'ipc_pid' => $ipcPid,
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * Update the last_used_at timestamp (keeps session alive during active use).
     */
    public function touch(string $sessionName): void
    {
        $now = time();
        try {
            Redis::pipeline(function ($pipe) use ($sessionName, $now) {
                $pipe->zadd(self::SESSIONS_KEY, $now, $sessionName);
                $pipe->hset(self::SESSION_PREFIX . $sessionName, 'last_used_at', $now);
                $pipe->expire(self::SESSION_PREFIX . $sessionName, 3600);
            });
        } catch (\Throwable) {
        }
    }

    /**
     * Release a session slot and kill its worker processes.
     */
    public function release(string $sessionName): void
    {
        try {
            $meta = Redis::hgetall(self::SESSION_PREFIX . $sessionName);
            $this->killSessionProcesses($sessionName, $meta);

            Redis::pipeline(function ($pipe) use ($sessionName) {
                $pipe->zrem(self::SESSIONS_KEY, $sessionName);
                $pipe->del(self::SESSION_PREFIX . $sessionName);
            });

            Log::info('MtprotoSessionRegistry: released', [
                'session' => $sessionName,
                'pid' => $meta['pid'] ?? 0,
                'ipc_pid' => $meta['ipc_pid'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('MtprotoSessionRegistry: release error', [
                'session' => $sessionName,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reap all sessions that are older than MAX_AGE_SECONDS or whose
     * processes are no longer running (orphans).
     *
     * @return array{reaped: int, orphans: int, zombies: int, zombie_parents: array<int,int>}
     */
    public function reap(): array
    {
        $reaped = 0;
        $orphans = 0;

        try {
            $sessions = Redis::zrangebyscore(self::SESSIONS_KEY, '-inf', '+inf', ['withscores' => true]);

            $now = time();
            $idleCutoff = $now - self::MAX_IDLE_SECONDS;
            $lifetimeCutoff = $now - self::MAX_LIFETIME_SECONDS;

            foreach ($sessions as $sessionName => $lastUsed) {
                $meta = Redis::hgetall(self::SESSION_PREFIX . $sessionName);
                $pid = (int) ($meta['pid'] ?? 0);
                $startedAt = (int) ($meta['started_at'] ?? $lastUsed);

                // Reap if orphaned metadata (zset entry without hash)
                if (empty($meta)) {
                    Log::info('MtprotoSessionRegistry: reaping session with missing metadata', [
                        'session' => $sessionName,
                    ]);
                    $this->release($sessionName);
                    $reaped++;
                    continue;
                }

                // Reap if idle too long (not used recently)
                if ((int) $lastUsed < $idleCutoff) {
                    Log::info('MtprotoSessionRegistry: reaping idle session', [
                        'session' => $sessionName,
                        'idle_seconds' => $now - (int) $lastUsed,
                        'pid' => $pid,
                    ]);
                    $this->release($sessionName);
                    $reaped++;
                    continue;
                }

                // Reap if exceeded max lifetime (regardless of recent use — prevents memory growth)
                if ($startedAt < $lifetimeCutoff) {
                    Log::info('MtprotoSessionRegistry: reaping session past max lifetime', [
                        'session' => $sessionName,
                        'lifetime_seconds' => $now - $startedAt,
                        'pid' => $pid,
                    ]);
                    $this->release($sessionName);
                    $reaped++;
                    continue;
                }

                // Reap if main process is dead (orphaned registry entry)
                if ($pid > 0 && !$this->isProcessAlive($pid)) {
                    Log::info('MtprotoSessionRegistry: reaping orphan session (process dead)', [
                        'session' => $sessionName,
                        'dead_pid' => $pid,
                    ]);
                    $this->release($sessionName);
                    $orphans++;
                    continue;
                }

                // Reap if worker was adopted by init (PPID=1) — its original
                // queue worker died and left it dangling. isProcessAlive() still
                // returns true because init keeps it alive, so the dead-PID
                // branch above never catches it.
                if ($pid > 0 && $this->getProcessPPid($pid) === 1) {
                    Log::info('MtprotoSessionRegistry: reaping adopted-by-init orphan', [
                        'session' => $sessionName,
                        'pid' => $pid,
                    ]);
                    $this->release($sessionName);
                    $orphans++;
                    continue;
                }
            }

            // Also find unregistered MadelineProto processes (not in registry)
            $orphans += $this->killUnregisteredWorkers($sessions);

            // Scan for zombie (defunct) processes and group them by parent.
            // We cannot waitpid() them from this process (we're not their parent),
            // but we CAN kill the parent — once the parent dies, init adopts the
            // zombies and reaps them immediately.
            [$zombieCount, $zombieParents] = $this->scanZombies();

            if ($zombieCount > 0) {
                Log::warning('MtprotoSessionRegistry: zombie processes detected', [
                    'total' => $zombieCount,
                    'by_parent' => $zombieParents,
                ]);

                // Kill parent processes that have accumulated zombie children.
                // This forces init to adopt and reap the defunct processes.
                // Only kill parents with 3+ zombies to avoid false positives
                // from normal short-lived transient zombies.
                foreach ($zombieParents as $ppid => $count) {
                    if ($count >= 3 && $ppid > 1) {
                        Log::warning('MtprotoSessionRegistry: killing zombie-leaking parent', [
                            'ppid' => $ppid,
                            'zombie_count' => $count,
                        ]);
                        $this->forceKill($ppid);
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('MtprotoSessionRegistry: reap error', ['error' => $e->getMessage()]);
            $zombieCount = 0;
            $zombieParents = [];
        }

        return [
            'reaped' => $reaped,
            'orphans' => $orphans,
            'zombies' => $zombieCount,
            'zombie_parents' => $zombieParents,
        ];
    }

    /**
     * Scan /proc for zombie (state=Z) processes and group by PPID.
     *
     * @return array{0:int, 1:array<int,int>} [total, ppid => count]
     */
    private function scanZombies(): array
    {
        if (!is_dir('/proc')) {
            return [0, []];
        }

        $dirs = @scandir('/proc');
        if ($dirs === false) {
            return [0, []];
        }

        $total = 0;
        $byParent = [];

        foreach ($dirs as $entry) {
            if (!is_numeric($entry)) {
                continue;
            }

            $status = @file_get_contents("/proc/{$entry}/status");
            if ($status === false) {
                continue;
            }

            if (!preg_match('/^State:\s+Z/m', $status)) {
                continue;
            }

            $total++;
            if (preg_match('/^PPid:\s+(\d+)/m', $status, $m)) {
                $ppid = (int) $m[1];
                $byParent[$ppid] = ($byParent[$ppid] ?? 0) + 1;
            }
        }

        return [$total, $byParent];
    }

    /**
     * Kill MadelineProto workers that are not tracked in the registry.
     * Reads /proc/ to find matching processes.
     */
    private function killUnregisteredWorkers(array $registeredSessions): int
    {
        $killed = 0;

        // Get all registered PIDs
        $registeredPids = [];
        foreach ($registeredSessions as $sessionName => $_) {
            $meta = Redis::hgetall(self::SESSION_PREFIX . $sessionName);
            if (($pid = (int) ($meta['pid'] ?? 0)) > 0) {
                $registeredPids[$pid] = true;
            }
            if (($ipcPid = (int) ($meta['ipc_pid'] ?? 0)) > 0) {
                $registeredPids[$ipcPid] = true;
            }
        }

        // Scan /proc/ for MadelineProto workers
        if (!is_dir('/proc')) {
            return 0;
        }

        $dirs = @scandir('/proc');
        if ($dirs === false) {
            return 0;
        }

        foreach ($dirs as $entry) {
            if (!is_numeric($entry)) {
                continue;
            }

            $pid = (int) $entry;
            if (isset($registeredPids[$pid])) {
                continue; // Known process
            }

            $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
            if ($cmdline === false) {
                continue;
            }

            // Match MadelineProto worker or IPC runner. Also match any process
            // whose cmdline references a .madeline session file but is not tracked
            // in the registry — catches workers whose proctitle format changed
            // across MadelineProto versions.
            $looksLikeWorker = str_contains($cmdline, 'MadelineProto worker')
                || str_contains($cmdline, 'madeline-ipc')
                || (str_contains($cmdline, '.madeline')
                    && str_contains($cmdline, '/telegram/sessions/'));

            if ($looksLikeWorker) {
                // Check age — only kill if older than 5 minutes
                $stat = @file_get_contents("/proc/{$pid}/stat");
                $startTime = $this->getProcessStartTime($pid);
                $uptime = $startTime > 0 ? time() - $startTime : 0;

                if ($uptime > 300) { // 5 minutes
                    Log::warning('MtprotoSessionRegistry: killing unregistered worker', [
                        'pid' => $pid,
                        'uptime_seconds' => $uptime,
                        'cmdline' => substr(str_replace("\0", ' ', $cmdline), 0, 200),
                    ]);

                    $this->forceKill($pid);
                    $killed++;
                }
            }
        }

        return $killed;
    }

    /**
     * Kill all processes associated with a session.
     */
    private function killSessionProcesses(string $sessionName, array $meta): void
    {
        $pid = (int) ($meta['pid'] ?? 0);
        $ipcPid = (int) ($meta['ipc_pid'] ?? 0);

        // Kill IPC child first, then main worker
        if ($ipcPid > 0) {
            $this->forceKill($ipcPid);
        }
        if ($pid > 0) {
            $this->forceKill($pid);
        }

        // Also search for any process using this session file (belt and suspenders)
        $sessionPath = storage_path("app/telegram/sessions/{$sessionName}.madeline");
        $this->killProcessesBySessionPath($sessionPath);
    }

    /**
     * Find and kill any process whose cmdline references the given session path.
     */
    private function killProcessesBySessionPath(string $sessionPath): void
    {
        if (!is_dir('/proc')) {
            return;
        }

        $dirs = @scandir('/proc');
        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $entry) {
            if (!is_numeric($entry)) {
                continue;
            }

            $pid = (int) $entry;
            if ($pid <= 1) {
                continue;
            }

            $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
            if ($cmdline !== false && str_contains($cmdline, $sessionPath)) {
                $this->forceKill($pid);
            }
        }
    }

    /**
     * Kill a process: SIGTERM first, then SIGKILL if still alive.
     */
    private function forceKill(int $pid): void
    {
        if ($pid <= 1 || !$this->isProcessAlive($pid)) {
            return;
        }

        // Don't kill our own process
        if ($pid === getmypid()) {
            return;
        }

        if (function_exists('posix_kill')) {
            posix_kill($pid, 15); // SIGTERM
            usleep(200_000); // 200ms grace

            if ($this->isProcessAlive($pid)) {
                posix_kill($pid, 9); // SIGKILL
            }
        }
    }

    /**
     * Check if a process is still running.
     */
    private function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        return is_dir("/proc/{$pid}");
    }

    /**
     * Read PPid from /proc/<pid>/status. Returns 0 on failure.
     * A return value of 1 means the process was adopted by init — i.e. its
     * original parent (the queue worker) died.
     */
    private function getProcessPPid(int $pid): int
    {
        if ($pid <= 0) {
            return 0;
        }

        $status = @file_get_contents("/proc/{$pid}/status");
        if ($status === false) {
            return 0;
        }

        if (preg_match('/^PPid:\s+(\d+)/m', $status, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * Get approximate process start time from /proc.
     */
    private function getProcessStartTime(int $pid): int
    {
        $stat = @file_get_contents("/proc/{$pid}/stat");
        if ($stat === false) {
            return 0;
        }

        // Field 22 (0-indexed: 21) is starttime in clock ticks
        $parts = explode(' ', $stat);
        $startTick = (int) ($parts[21] ?? 0);
        if ($startTick <= 0) {
            return 0;
        }

        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime === false) {
            return 0;
        }

        $uptimeSeconds = (float) explode(' ', $uptime)[0];
        $hz = 100; // Standard for most Linux kernels
        $processUptimeSeconds = $uptimeSeconds - ($startTick / $hz);

        return (int) (time() - $processUptimeSeconds);
    }

    /**
     * Get current active session count.
     */
    public function activeCount(): int
    {
        try {
            return (int) Redis::zcard(self::SESSIONS_KEY);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Get all active sessions with metadata (for diagnostics).
     */
    public function listActive(): array
    {
        try {
            $sessions = Redis::zrangebyscore(self::SESSIONS_KEY, '-inf', '+inf', ['withscores' => true]);
            $result = [];

            foreach ($sessions as $sessionName => $lastUsed) {
                $meta = Redis::hgetall(self::SESSION_PREFIX . $sessionName);
                $result[] = array_merge($meta, [
                    'session' => $sessionName,
                    'last_used_at' => (int) $lastUsed,
                    'age_seconds' => time() - (int) ($meta['started_at'] ?? $lastUsed),
                ]);
            }

            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get the max sessions cap.
     */
    public static function maxSessions(): int
    {
        return self::MAX_SESSIONS;
    }
}
