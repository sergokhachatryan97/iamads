<?php

namespace App\Services\HealthCheck;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ServerHealthCheckService
{
    /**
     * Run all health checks and dispatch alerts for any that are in a bad state.
     *
     * @return array<string, array{status: string, message: string}>
     */
    public function run(): array
    {
        $results = [
            'cpu' => $this->checkCpu(),
            'memory' => $this->checkMemory(),
            'disk' => $this->checkDisk(),
            'queue' => $this->checkQueue(),
        ];

        foreach ($results as $name => $result) {
            if ($result['status'] === 'bad') {
                $this->alertWithCooldown($name, $result['message']);
            }
        }

        return $results;
    }

    protected function checkCpu(): array
    {
        $cores = $this->cpuCores();

        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $usage = $this->sampleCpuUsage();
        $runqueue = $this->readRunqueue();

        // If we couldn't get any real signal, report unknown.
        if ($load === false && $usage === null) {
            return ['status' => 'unknown', 'message' => 'no CPU stats available on this platform'];
        }

        $loadPerCoreThreshold = (float) config('health.thresholds.cpu_load_per_core');
        $usagePercentThreshold = (float) config('health.thresholds.cpu_usage_percent');
        $runqueueMultiplier = (float) config('health.thresholds.cpu_runqueue_multiplier');

        // Determine reasons the CPU is unhealthy.
        $reasons = [];
        $isBad = false;

        $perCore = ($load !== false && $cores > 0) ? $load[0] / $cores : null;
        if ($perCore !== null && $perCore > $loadPerCoreThreshold) {
            $isBad = true;
            $reasons[] = sprintf('load/core=%.2f > %.2f', $perCore, $loadPerCoreThreshold);
        }

        if ($usage !== null) {
            $busy = $usage['user'] + $usage['system'];
            if ($usagePercentThreshold > 0 && $busy > $usagePercentThreshold) {
                $isBad = true;
                $reasons[] = sprintf('busy=%.1f%% > %.1f%%', $busy, $usagePercentThreshold);
            }
        }

        if ($runqueue !== null && $cores > 0) {
            $ratio = $runqueue / $cores;
            if ($runqueueMultiplier > 0 && $ratio > $runqueueMultiplier) {
                $isBad = true;
                $reasons[] = sprintf('runqueue=%d (%.1fx cores)', $runqueue, $ratio);
            }
        }

        // Build a detailed human-readable message either way.
        $detail = $this->formatCpuDetail($cores, $load, $usage, $runqueue);

        if ($isBad) {
            $why = $this->classifyCpu($usage);
            return [
                'status' => 'bad',
                'message' => "\xF0\x9F\x94\xA5 CPU high ({$why}): {$detail} | trip: ".implode(', ', $reasons),
            ];
        }

        return ['status' => 'ok', 'message' => "CPU ok: {$detail}"];
    }

    /**
     * Sample /proc/stat twice with a short delay and compute %user, %system, %idle, %iowait.
     *
     * @return array{user: float, system: float, idle: float, iowait: float, steal: float}|null
     */
    protected function sampleCpuUsage(): ?array
    {
        $first = $this->readCpuStat();
        if ($first === null) {
            return null;
        }

        // 300ms sample window — short enough to not slow the health check,
        // long enough to produce stable numbers.
        usleep(300_000);

        $second = $this->readCpuStat();
        if ($second === null) {
            return null;
        }

        $delta = [];
        foreach ($first as $k => $v) {
            $delta[$k] = max(0, ($second[$k] ?? 0) - $v);
        }

        $total = array_sum($delta);
        if ($total <= 0) {
            return null;
        }

        return [
            'user' => (($delta['user'] + $delta['nice']) / $total) * 100,
            'system' => (($delta['system'] + $delta['irq'] + $delta['softirq']) / $total) * 100,
            'idle' => ($delta['idle'] / $total) * 100,
            'iowait' => ($delta['iowait'] / $total) * 100,
            'steal' => ($delta['steal'] / $total) * 100,
        ];
    }

    /**
     * Read aggregate CPU counters from /proc/stat.
     *
     * @return array{user:int,nice:int,system:int,idle:int,iowait:int,irq:int,softirq:int,steal:int}|null
     */
    protected function readCpuStat(): ?array
    {
        $stat = @file_get_contents('/proc/stat');
        if ($stat === false) {
            return null;
        }

        if (! preg_match('/^cpu\s+([\d\s]+)/m', $stat, $m)) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($m[1]));
        if (count($parts) < 8) {
            return null;
        }

        return [
            'user' => (int) $parts[0],
            'nice' => (int) $parts[1],
            'system' => (int) $parts[2],
            'idle' => (int) $parts[3],
            'iowait' => (int) $parts[4],
            'irq' => (int) $parts[5],
            'softirq' => (int) $parts[6],
            'steal' => (int) $parts[7],
        ];
    }

    /**
     * Read the current runnable-process count from /proc/loadavg (4th field: "running/total").
     */
    protected function readRunqueue(): ?int
    {
        $loadavg = @file_get_contents('/proc/loadavg');
        if ($loadavg === false) {
            return null;
        }

        // Format: "1.23 2.34 3.45 4/567 12345"
        if (! preg_match('#(\d+)/\d+#', $loadavg, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * Build a compact multi-line detail string for the alert/log.
     */
    protected function formatCpuDetail(int $cores, array|false $load, ?array $usage, ?int $runqueue): string
    {
        $parts = [];
        $parts[] = sprintf('%d cores', $cores);

        if ($load !== false) {
            $perCore = $cores > 0 ? $load[0] / $cores : $load[0];
            $parts[] = sprintf('load %.2f/%.2f/%.2f (%.2f/core)', $load[0], $load[1], $load[2], $perCore);
        }

        if ($usage !== null) {
            $parts[] = sprintf(
                'us=%.0f%% sy=%.0f%% id=%.0f%% wa=%.0f%%',
                $usage['user'], $usage['system'], $usage['idle'], $usage['iowait']
            );
            if ($usage['steal'] > 1.0) {
                $parts[] = sprintf('st=%.0f%%', $usage['steal']);
            }
        }

        if ($runqueue !== null) {
            $ratio = $cores > 0 ? $runqueue / $cores : 0;
            $parts[] = sprintf('runq=%d (%.1fx)', $runqueue, $ratio);
        }

        return implode(' | ', $parts);
    }

    /**
     * Best-effort classification of *why* CPU looks bad, to put in the alert prefix.
     */
    protected function classifyCpu(?array $usage): string
    {
        if ($usage === null) {
            return 'load avg only';
        }

        if ($usage['iowait'] >= 20) {
            return 'I/O wait';
        }
        if ($usage['steal'] >= 10) {
            return 'steal (noisy neighbor)';
        }
        if ($usage['system'] >= 25) {
            return 'kernel/syscalls — likely process oversubscription';
        }
        if ($usage['user'] >= 70) {
            return 'user CPU';
        }
        if (($usage['user'] + $usage['system']) >= 80) {
            return 'CPU saturated';
        }

        return 'load avg elevated';
    }

    protected function cpuCores(): int
    {
        $cpuinfo = @file_get_contents('/proc/cpuinfo');
        if ($cpuinfo === false) {
            return 1;
        }

        $count = preg_match_all('/^processor\s*:/m', $cpuinfo);

        return max(1, (int) $count);
    }

    protected function checkMemory(): array
    {
        $meminfo = @file_get_contents('/proc/meminfo');
        if ($meminfo === false) {
            return ['status' => 'unknown', 'message' => '/proc/meminfo unavailable (non-Linux?)'];
        }

        if (! preg_match('/MemTotal:\s+(\d+)\s*kB/', $meminfo, $totalMatch) ||
            ! preg_match('/MemAvailable:\s+(\d+)\s*kB/', $meminfo, $availMatch)) {
            return ['status' => 'unknown', 'message' => 'failed to parse /proc/meminfo'];
        }

        $totalKb = (int) $totalMatch[1];
        $availKb = (int) $availMatch[1];
        if ($totalKb <= 0) {
            return ['status' => 'unknown', 'message' => 'MemTotal is zero'];
        }

        $usedPercent = (($totalKb - $availKb) / $totalKb) * 100;
        $threshold = (float) config('health.thresholds.memory_percent');

        if ($usedPercent > $threshold) {
            return [
                'status' => 'bad',
                'message' => sprintf(
                    "\xF0\x9F\x92\xBE Memory high: %.1f%% used (threshold %.1f%%) | total=%s available=%s",
                    $usedPercent, $threshold,
                    $this->formatBytes($totalKb * 1024),
                    $this->formatBytes($availKb * 1024)
                ),
            ];
        }

        return [
            'status' => 'ok',
            'message' => sprintf('memory ok: %.1f%% used', $usedPercent),
        ];
    }

    protected function checkDisk(): array
    {
        $path = config('health.disk_path') ?: base_path();

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);
        if ($free === false || $total === false || $total <= 0) {
            return ['status' => 'unknown', 'message' => "failed to read disk stats for {$path}"];
        }

        $usedPercent = (($total - $free) / $total) * 100;
        $threshold = (float) config('health.thresholds.disk_percent');

        if ($usedPercent > $threshold) {
            return [
                'status' => 'bad',
                'message' => sprintf(
                    "\xF0\x9F\x92\xBD Disk high: %.1f%% used on %s (threshold %.1f%%) | free=%s of %s",
                    $usedPercent, $path, $threshold,
                    $this->formatBytes((int) $free),
                    $this->formatBytes((int) $total)
                ),
            ];
        }

        return [
            'status' => 'ok',
            'message' => sprintf('disk ok: %.1f%% used', $usedPercent),
        ];
    }

    protected function checkQueue(): array
    {
        // 1) Horizon supervisors must be running if Horizon is installed.
        if (class_exists(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)) {
            try {
                /** @var \Laravel\Horizon\Contracts\MasterSupervisorRepository $repo */
                $repo = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class);
                $masters = $repo->all();

                if (empty($masters)) {
                    return [
                        'status' => 'bad',
                        'message' => "\xE2\x9A\x99\xEF\xB8\x8F Horizon not running (no master supervisors reporting)",
                    ];
                }

                foreach ($masters as $master) {
                    if (($master->status ?? null) !== 'running') {
                        return [
                            'status' => 'bad',
                            'message' => sprintf(
                                "\xE2\x9A\x99\xEF\xB8\x8F Horizon supervisor %s status: %s",
                                $master->name ?? 'unknown',
                                $master->status ?? 'unknown'
                            ),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[health] Horizon check failed: '.$e->getMessage());
            }
        }

        // 2) Default queue backlog must be within limits.
        try {
            $size = Queue::size();
            $max = (int) config('health.thresholds.queue_size_max');
            if ($max > 0 && $size > $max) {
                return [
                    'status' => 'bad',
                    'message' => sprintf(
                        "\xF0\x9F\x93\xA6 Queue backlog high: %d jobs (threshold %d)",
                        $size, $max
                    ),
                ];
            }

            return ['status' => 'ok', 'message' => "queue ok: {$size} jobs"];
        } catch (\Throwable $e) {
            return ['status' => 'unknown', 'message' => 'queue size check failed: '.$e->getMessage()];
        }
    }

    protected function alertWithCooldown(string $metric, string $message): void
    {
        $cooldown = (int) config('health.alert_cooldown_seconds', 900);
        $cacheKey = "health_alert:{$metric}";

        if (Cache::has($cacheKey)) {
            return;
        }

        if ($this->sendTelegram($message)) {
            Cache::put($cacheKey, 1, $cooldown);
        }
    }

    protected function sendTelegram(string $message): bool
    {
        $token = config('health.telegram.bot_token');
        $chatId = config('health.telegram.chat_id');

        if (! $token || ! $chatId) {
            Log::warning('[health] Alert not sent: missing TELEGRAM_BOT_TOKEN or HEALTH_ALERT_CHAT_ID', [
                'alert' => $message,
            ]);

            return false;
        }

        $host = gethostname() ?: 'unknown-host';
        $env = app()->environment();
        $text = "[{$env}@{$host}] {$message}";

        try {
            $response = Http::timeout(10)->asForm()->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                ['chat_id' => $chatId, 'text' => $text]
            );

            if (! $response->successful()) {
                Log::warning('[health] Telegram alert failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('[health] Telegram alert exception: '.$e->getMessage());

            return false;
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $val = (float) $bytes;
        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }

        return sprintf('%.1f %s', $val, $units[$i]);
    }
}
