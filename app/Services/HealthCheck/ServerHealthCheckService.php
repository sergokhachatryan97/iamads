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
            'nginx' => $this->checkNginx(),
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

    protected function checkNginx(): array
    {
        // 1) Read worker_connections from nginx config
        $maxConnections = $this->readNginxWorkerConnections();
        $workerProcesses = $this->readNginxWorkerProcesses();

        // Total capacity = workers × connections_per_worker
        $totalCapacity = $maxConnections * $workerProcesses;

        if ($totalCapacity <= 0) {
            return ['status' => 'unknown', 'message' => 'could not determine nginx capacity'];
        }

        // 2) Count current TCP connections via /proc/net/sockstat (no stub_status needed)
        $activeConnections = $this->countTcpConnections();

        if ($activeConnections === null) {
            // Fallback: try nginx stub_status on localhost
            $activeConnections = $this->readNginxStubStatus();
        }

        if ($activeConnections === null) {
            return ['status' => 'unknown', 'message' => 'could not read connection count'];
        }

        $usagePercent = ($activeConnections / $totalCapacity) * 100;
        $threshold = (float) config('health.thresholds.nginx_connections_percent', 80);

        // 3) Check if nginx process is running
        $nginxRunning = $this->isNginxRunning();
        if (! $nginxRunning) {
            return [
                'status' => 'bad',
                'message' => "\xF0\x9F\x9A\xA8 Nginx is NOT running!",
            ];
        }

        if ($usagePercent > $threshold) {
            // Include diagnostics so you know WHO is eating connections
            $diag = $this->getConnectionDiagnostics();

            return [
                'status' => 'bad',
                'message' => sprintf(
                    "\xF0\x9F\x8C\x90 Nginx connections high: %d / %d (%.1f%%, threshold %.0f%%)\nworkers=%d x conn=%d\n%s",
                    $activeConnections, $totalCapacity, $usagePercent, $threshold,
                    $workerProcesses, $maxConnections, $diag
                ),
            ];
        }

        return [
            'status' => 'ok',
            'message' => sprintf(
                'nginx ok: %d connections (%.1f%% of %d capacity)',
                $activeConnections, $usagePercent, $totalCapacity
            ),
        ];
    }

    /**
     * Parse worker_connections from nginx.conf.
     */
    protected function readNginxWorkerConnections(): int
    {
        $paths = ['/etc/nginx/nginx.conf', '/usr/local/nginx/conf/nginx.conf'];

        foreach ($paths as $path) {
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }

            if (preg_match('/worker_connections\s+(\d+)\s*;/', $content, $m)) {
                return (int) $m[1];
            }
        }

        return (int) config('health.thresholds.nginx_worker_connections_fallback', 8192);
    }

    /**
     * Parse worker_processes from nginx.conf. "auto" resolves to CPU core count.
     */
    protected function readNginxWorkerProcesses(): int
    {
        $paths = ['/etc/nginx/nginx.conf', '/usr/local/nginx/conf/nginx.conf'];

        foreach ($paths as $path) {
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }

            if (preg_match('/worker_processes\s+(\S+)\s*;/', $content, $m)) {
                $value = $m[1];
                if ($value === 'auto') {
                    return $this->cpuCores();
                }

                return max(1, (int) $value);
            }
        }

        return $this->cpuCores();
    }

    /**
     * Count established TCP connections from /proc/net/sockstat (Linux).
     * This is instant and doesn't require any nginx module.
     */
    protected function countTcpConnections(): ?int
    {
        // /proc/net/sockstat has a line like: "TCP: inuse 245 orphan 0 tw 12 alloc 260 mem 30"
        $sockstat = @file_get_contents('/proc/net/sockstat');
        if ($sockstat === false) {
            return null;
        }

        if (preg_match('/TCP:\s+inuse\s+(\d+)/', $sockstat, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Fallback: read active connections from nginx stub_status on localhost.
     * Requires stub_status to be configured (see setup instructions).
     */
    protected function readNginxStubStatus(): ?int
    {
        try {
            $response = Http::timeout(3)->get('http://127.0.0.1/nginx_status');
            if ($response->successful() && preg_match('/Active connections:\s*(\d+)/', $response->body(), $m)) {
                return (int) $m[1];
            }
        } catch (\Throwable) {
        }

        return null;
    }

    protected function isNginxRunning(): bool
    {
        $output = @shell_exec('pgrep -c nginx 2>/dev/null');

        return $output !== null && (int) trim($output) > 0;
    }

    /**
     * Collect connection diagnostics: top IPs, connection states, top ports.
     * Included in the alert so you immediately know WHO is eating connections.
     */
    protected function getConnectionDiagnostics(): string
    {
        $parts = [];

        // Connection states (ESTABLISHED, TIME_WAIT, etc.)
        $states = $this->getConnectionStates();
        if (! empty($states)) {
            $stateStr = implode(', ', array_map(
                fn ($count, $state) => "{$state}={$count}",
                $states, array_keys($states)
            ));
            $parts[] = "States: {$stateStr}";
        }

        // Top 5 IPs by connection count
        $topIps = $this->getTopConnectionIps(5);
        if (! empty($topIps)) {
            $ipStr = implode(', ', array_map(
                fn ($count, $ip) => "{$ip}({$count})",
                $topIps, array_keys($topIps)
            ));
            $parts[] = "Top IPs: {$ipStr}";
        }

        // Top ports
        $topPorts = $this->getTopConnectionPorts(5);
        if (! empty($topPorts)) {
            $portStr = implode(', ', array_map(
                fn ($count, $port) => ":{$port}({$count})",
                $topPorts, array_keys($topPorts)
            ));
            $parts[] = "Ports: {$portStr}";
        }

        return implode("\n", $parts);
    }

    /**
     * Count connections by TCP state from /proc/net/tcp.
     */
    protected function getConnectionStates(): array
    {
        // Use `ss` for reliable state counts
        $output = @shell_exec('ss -tan state established 2>/dev/null | wc -l');
        $established = $output !== null ? max(0, (int) trim($output) - 1) : 0;

        $output = @shell_exec('ss -tan state time-wait 2>/dev/null | wc -l');
        $timeWait = $output !== null ? max(0, (int) trim($output) - 1) : 0;

        $output = @shell_exec('ss -tan state close-wait 2>/dev/null | wc -l');
        $closeWait = $output !== null ? max(0, (int) trim($output) - 1) : 0;

        $output = @shell_exec('ss -tan state syn-recv 2>/dev/null | wc -l');
        $synRecv = $output !== null ? max(0, (int) trim($output) - 1) : 0;

        $states = [];
        if ($established > 0) $states['ESTAB'] = $established;
        if ($timeWait > 0) $states['TIME_WAIT'] = $timeWait;
        if ($closeWait > 0) $states['CLOSE_WAIT'] = $closeWait;
        if ($synRecv > 0) $states['SYN_RECV'] = $synRecv;

        arsort($states);

        return $states;
    }

    /**
     * Top N remote IPs by number of established connections.
     */
    protected function getTopConnectionIps(int $limit): array
    {
        $output = @shell_exec("ss -tn state established 2>/dev/null | awk 'NR>1{print \$5}' | cut -d: -f1 | sort | uniq -c | sort -rn | head -{$limit}");
        if ($output === null || trim($output) === '') {
            return [];
        }

        $ips = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if (preg_match('/^\s*(\d+)\s+(.+)$/', $line, $m)) {
                $ips[$m[2]] = (int) $m[1];
            }
        }

        return $ips;
    }

    /**
     * Top N local ports by connection count (shows which services are busy).
     */
    protected function getTopConnectionPorts(int $limit): array
    {
        $output = @shell_exec("ss -tn state established 2>/dev/null | awk 'NR>1{print \$4}' | grep -oP ':\\K\\d+$' | sort | uniq -c | sort -rn | head -{$limit}");
        if ($output === null || trim($output) === '') {
            return [];
        }

        $ports = [];
        foreach (explode("\n", trim($output)) as $line) {
            $line = trim($line);
            if (preg_match('/^\s*(\d+)\s+(\d+)$/', $line, $m)) {
                $portLabel = match ($m[2]) {
                    '80' => '80/http',
                    '443' => '443/https',
                    '3306' => '3306/mysql',
                    '6379' => '6379/redis',
                    '22' => '22/ssh',
                    '8083' => '8083/panel',
                    default => $m[2],
                };
                $ports[$portLabel] = (int) $m[1];
            }
        }

        return $ports;
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
