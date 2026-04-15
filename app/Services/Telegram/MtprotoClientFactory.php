<?php

namespace App\Services\Telegram;

use App\Models\MtprotoTelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Connection;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;
use danog\MadelineProto\Stream\MTProtoTransport\ObfuscatedStream;
use Illuminate\Support\Facades\Log;

class MtprotoClientFactory
{
    /**
     * Active API instances keyed by session name.
     * Kept alive for the lifetime of the process (queue worker / artisan command).
     * Eviction is managed by MtprotoSessionRegistry.
     */
    private array $instances = [];

    public function __construct(
        private MtprotoSessionRegistry $registry
    ) {}

    /**
     * AUTHORIZE MODE (interactive CLI only)
     * Bypasses the session registry — used only for one-time account setup.
     */
    public function makeForAuthorize(MtprotoTelegramAccount $account, bool $useProxy = false): API
    {
        $sessionPath = $this->sessionPath($account);

        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0775, true);
        }

        $settings = $this->buildSettings($account, $useProxy);
        return new API($sessionPath, $settings);
    }

    /**
     * RUNTIME MODE — the main entry point for all production use.
     *
     * Acquires a slot from the session registry (enforces global cap),
     * reuses an existing instance if available, or creates a new one.
     * Returns null if the global cap is reached and eviction failed.
     */
    public function makeForRuntime(MtprotoTelegramAccount $account): ?API
    {
        $sessionName = $this->sessionName($account);
        $sessionPath = $this->sessionPath($account);

        if (!file_exists($sessionPath)) {
            throw new \RuntimeException("SESSION_NOT_AUTHORIZED for account {$account->id} ({$sessionName})");
        }

        // Reuse existing in-process instance
        if (isset($this->instances[$sessionName])) {
            $this->registry->touch($sessionName);

            Log::debug('MtprotoClientFactory: reusing instance', [
                'session' => $sessionName,
                'account_id' => $account->id,
            ]);

            return $this->instances[$sessionName];
        }

        // Acquire slot from registry (may evict oldest if at cap)
        if (!$this->registry->acquire($sessionName, $account->id)) {
            Log::warning('MtprotoClientFactory: session cap reached, cannot acquire', [
                'session' => $sessionName,
                'account_id' => $account->id,
                'active' => $this->registry->activeCount(),
                'max' => MtprotoSessionRegistry::maxSessions(),
            ]);
            return null;
        }

        // MadelineProto IPC: log if proc_open is disabled
        if ($this->isProcOpenDisabled()) {
            static $warned = false;
            if (!$warned) {
                $warned = true;
                Log::warning('MTProto: proc_open is disabled; MadelineProto IPC may fail.');
            }
        }

        $settings = $this->buildSettings($account, true);
        $api = new API($sessionPath, $settings);

        // Track the instance
        $this->instances[$sessionName] = $api;

        // Try to capture the worker PID after construction
        $this->captureAndRegisterPid($sessionName);

        Log::info('MtprotoClientFactory: created new instance', [
            'session' => $sessionName,
            'account_id' => $account->id,
            'active_sessions' => $this->registry->activeCount(),
        ]);

        return $api;
    }

    /**
     * Stop and release a runtime instance. Ensures the IPC worker is killed.
     */
    public function releaseInstance(MtprotoTelegramAccount $account): void
    {
        $sessionName = $this->sessionName($account);
        $this->releaseBySessionName($sessionName);
    }

    /**
     * Stop and release by session name (used by the reaper).
     */
    public function releaseBySessionName(string $sessionName): void
    {
        if (isset($this->instances[$sessionName])) {
            $api = $this->instances[$sessionName];

            try {
                if (method_exists($api, 'stop')) {
                    $api->stop();
                }
            } catch (\Throwable $e) {
                Log::debug('MtprotoClientFactory: stop() exception (expected)', [
                    'session' => $sessionName,
                    'error' => substr($e->getMessage(), 0, 100),
                ]);
            }

            unset($this->instances[$sessionName]);
        }

        // Registry handles PID kill + cleanup
        $this->registry->release($sessionName);
    }

    /**
     * Forget a runtime instance on error (same as releaseInstance).
     * Backward-compatible alias used by existing code.
     */
    public function forgetRuntimeInstance(?MtprotoTelegramAccount $account): void
    {
        if ($account === null) {
            return;
        }
        $this->releaseInstance($account);
    }

    /**
     * After $api->start() is called, capture the IPC worker PID and register it.
     * Call this from the pool service after start().
     */
    public function captureAndRegisterPid(string $sessionName): void
    {
        try {
            $workerPid = getmypid();
            $this->registry->registerPids($sessionName, $workerPid);
        } catch (\Throwable) {
        }
    }

    /**
     * Capture PIDs by reading /proc/ for the session path.
     * Call after $api->start() to find the actual MadelineProto worker PID.
     */
    public function captureWorkerPid(string $sessionName): void
    {
        $sessionPath = storage_path("app/telegram/sessions/{$sessionName}.madeline");

        if (!is_dir('/proc')) {
            return;
        }

        try {
            $dirs = @scandir('/proc');
            if ($dirs === false) {
                return;
            }

            $myPid = getmypid();

            foreach ($dirs as $entry) {
                if (!is_numeric($entry)) {
                    continue;
                }

                $pid = (int) $entry;
                if ($pid <= 1 || $pid === $myPid) {
                    continue;
                }

                $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
                if ($cmdline === false) {
                    continue;
                }

                if (str_contains($cmdline, $sessionPath) && str_contains($cmdline, 'MadelineProto worker')) {
                    // Find the IPC child
                    $ipcPid = 0;
                    $children = @file_get_contents("/proc/{$pid}/task/{$pid}/children");
                    if ($children !== false) {
                        $childPids = array_filter(array_map('intval', explode(' ', trim($children))));
                        $ipcPid = $childPids[0] ?? 0;
                    }

                    $this->registry->registerPids($sessionName, $pid, $ipcPid);

                    Log::debug('MtprotoClientFactory: captured worker PID', [
                        'session' => $sessionName,
                        'worker_pid' => $pid,
                        'ipc_pid' => $ipcPid,
                    ]);
                    return;
                }
            }
        } catch (\Throwable) {
        }
    }

    // =========================================================================
    //  Internal helpers
    // =========================================================================

    public function sessionName(MtprotoTelegramAccount $account): string
    {
        return $account->session_name ?: ('acc_' . str_pad((string) $account->id, 3, '0', STR_PAD_LEFT));
    }

    private function sessionPath(MtprotoTelegramAccount $account): string
    {
        $name = $this->sessionName($account);
        $path = storage_path("app/telegram/sessions/{$name}.madeline");

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        return $path;
    }

    private function buildSettings(MtprotoTelegramAccount $account, bool $useProxy): Settings
    {
        $apiId = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');

        if (!$apiId || !$apiHash) {
            throw new \RuntimeException('Missing services.telegram.api_id or services.telegram.api_hash');
        }

        $settings = new Settings();
        $settings->setAppInfo(new AppInfo($apiId, $apiHash));

        $connection = new Connection();

        if ($useProxy && $account->proxy_type) {
            $this->applyProxy($connection, $account);
        }

        $connection->setUseDoH(false);
        $settings->setConnection($connection);

        return $settings;
    }

    private function applyProxy(Connection $connection, MtprotoTelegramAccount $account): void
    {
        if (empty($account->proxy_host) || empty($account->proxy_port)) {
            throw new \RuntimeException("PROXY_INVALID for account {$account->id}");
        }

        $type = (string) $account->proxy_type;

        switch ($type) {
            case 'socks5':
                $connection->addProxy(SocksProxy::class, [
                    'address' => (string) $account->proxy_host,
                    'port' => (int) $account->proxy_port,
                    'username' => $account->proxy_user ?: null,
                    'password' => $account->proxy_pass ?: null,
                ]);
                break;

            case 'http':
                $connection->addProxy(HttpProxy::class, [
                    'address' => (string) $account->proxy_host,
                    'port' => (int) $account->proxy_port,
                    'username' => $account->proxy_user ?: null,
                    'password' => $account->proxy_pass ?: null,
                ]);
                break;

            case 'mtproxy':
                if (empty($account->proxy_secret)) {
                    throw new \RuntimeException("PROXY_INVALID (missing secret) for account {$account->id}");
                }
                $connection->addProxy(ObfuscatedStream::class, [
                    'address' => (string) $account->proxy_host,
                    'port' => (int) $account->proxy_port,
                    'secret' => (string) $account->proxy_secret,
                ]);
                break;

            default:
                throw new \RuntimeException("PROXY_INVALID (unknown type {$type}) for account {$account->id}");
        }
    }

    private function isProcOpenDisabled(): bool
    {
        if (!function_exists('proc_open')) {
            return true;
        }
        $disabled = ini_get('disable_functions');
        return $disabled !== false && $disabled !== '' && str_contains(str_replace(' ', '', $disabled), 'proc_open');
    }
}
