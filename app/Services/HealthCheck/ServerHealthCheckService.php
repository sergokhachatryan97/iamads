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
        if (! function_exists('sys_getloadavg')) {
            return ['status' => 'unknown', 'message' => 'sys_getloadavg() unavailable'];
        }

        $load = sys_getloadavg();
        if ($load === false) {
            return ['status' => 'unknown', 'message' => 'failed to read load average'];
        }

        $cores = $this->cpuCores();
        $perCore = $cores > 0 ? $load[0] / $cores : $load[0];
        $threshold = (float) config('health.thresholds.cpu_load_per_core');

        if ($perCore > $threshold) {
            return [
                'status' => 'bad',
                'message' => sprintf(
                    "\xF0\x9F\x94\xA5 CPU high: load %.2f / %.2f / %.2f on %d cores (%.2f per core, threshold %.2f)",
                    $load[0], $load[1], $load[2], $cores, $perCore, $threshold
                ),
            ];
        }

        return [
            'status' => 'ok',
            'message' => sprintf('CPU ok: %.2f per core (%d cores)', $perCore, $cores),
        ];
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
