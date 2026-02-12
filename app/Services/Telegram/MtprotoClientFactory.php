<?php
namespace App\Services\Telegram;

use App\Models\MtprotoTelegramAccount;

use danog\MadelineProto\API;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Connection;

use danog\MadelineProto\Stream\Proxy\SocksProxy;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\MTProtoTransport\ObfuscatedStream;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MtprotoClientFactory
{
    /**
     * Runtime in-process short-lived cache (SAFE only when paired with per-account lock).
     * key => ['api' => API, 'expires_at' => float]
     */
    private array $runtimeInstances = [];

    /**
     * Reuse the same API instance for a short time in the same worker process
     * to avoid start/stop overhead on heavy calls.
     */
    private int $runtimeReuseTtlSeconds = 20;

    /**
     * AUTHORIZE MODE (interactive)
     * - Can create a new session file
     * - Can ask for code/password in CLI
     * - No in-memory cache (keep simple)
     * - Proxy optional (default off)
     */
    public function makeForAuthorize(MtprotoTelegramAccount $account, bool $useProxy = false): API
    {
        return $this->createApi(
            account: $account,
            requireSessionFile: false,
            useProxy: $useProxy,
            cacheMode: false
        );
    }

    /**
     * RUNTIME MODE (non-interactive, heavy calls)
     * - Requires existing session file (fail-fast)
     * - Applies proxy from account config (if set)
     * - Uses short-lived reuse cache for speed (paired with per-account lock!)
     */
    public function makeForRuntime(MtprotoTelegramAccount $account): API
    {
            return $this->createApi(
                account: $account,
                requireSessionFile: true,
                useProxy: true,
                cacheMode: true
            );
        }


    /**
     * Forget/stop runtime instance (call on errors).
     */
    public function forgetRuntimeInstance(MtprotoTelegramAccount $account): void
    {
        $key = $this->runtimeCacheKey($account);
        if (!isset($this->runtimeInstances[$key])) return;

        try {
            $api = $this->runtimeInstances[$key]['api'] ?? null;
            if ($api instanceof API && method_exists($api, 'stop')) {
                $api->stop();
            }
        } catch (\Throwable $e) {
        }

        unset($this->runtimeInstances[$key]);
    }

    /**
     * Internal creator used by both modes.
     */
    private function createApi(
        MtprotoTelegramAccount $account,
        bool $requireSessionFile,
        bool $useProxy,
        bool $cacheMode
    ): API {
        $apiId   = (int) config('services.telegram.api_id');
        $apiHash = (string) config('services.telegram.api_hash');

        if (!$apiId || !$apiHash) {
            throw new \RuntimeException('Missing services.telegram.api_id or services.telegram.api_hash');
        }

        $sessionName = $account->session_name ?: ('acc_' . str_pad((string)$account->id, 3, '0', STR_PAD_LEFT));
        $sessionPath = storage_path("app/telegram/sessions/{$sessionName}.madeline");

        if (!is_dir(dirname($sessionPath))) {
            mkdir(dirname($sessionPath), 0775, true);
        }


        // Runtime must never go interactive
        if ($requireSessionFile && !file_exists($sessionPath)) {
            throw new \RuntimeException("SESSION_NOT_AUTHORIZED for account {$account->id} ({$sessionName})");
        }

        // MadelineProto uses proc_open for IPC; log once if disabled (queue workers need it)
        if ($requireSessionFile && $this->isProcOpenDisabled()) {
            static $warned = false;
            if (!$warned) {
                $warned = true;
                Log::warning('MTProto: proc_open is disabled; MadelineProto IPC may fail. Enable proc_open and relax open_basedir for queue workers.');
            }
        }

        // Runtime short cache (ONLY SAFE with per-account lock)
        if ($cacheMode) {
            $key = $this->runtimeCacheKey($account);
            $now = microtime(true);

            if (isset($this->runtimeInstances[$key])) {
                $row = $this->runtimeInstances[$key];
                if (($row['expires_at'] ?? 0) > $now && ($row['api'] ?? null) instanceof API) {
                    return $row['api'];
                }

                // expired -> stop & drop
                try {
                    $api = $row['api'] ?? null;
                    if ($api instanceof API && method_exists($api, 'stop')) $api->stop();
                } catch (\Throwable $e) {}
                unset($this->runtimeInstances[$key]);
            }
        }

        $settings = new Settings();
        $settings->setAppInfo(new AppInfo($apiId, $apiHash));

        $connection = new Connection();

        if ($useProxy && $account->proxy_type) {
            $this->applyProxy($connection, $account);
        }
        $connection->setUseDoH(false);
        $settings->setConnection($connection);

        $api = new API($sessionPath, $settings);
        // Do NOT call start() here; pool/service must call start() before use (allows throttle/jitter before start).

        if ($cacheMode) {
            $key = $this->runtimeCacheKey($account);
            $this->runtimeInstances[$key] = [
                'api' => $api,
                'expires_at' => microtime(true) + $this->runtimeReuseTtlSeconds,
            ];
        }

        return $api;
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
                    'address'  => (string) $account->proxy_host,
                    'port'     => (int) $account->proxy_port,
                    'username' => $account->proxy_user ?: null,
                    'password' => $account->proxy_pass ?: null,
                ]);
                break;

            case 'http':
                $connection->addProxy(HttpProxy::class, [
                    'address'  => (string) $account->proxy_host,
                    'port'     => (int) $account->proxy_port,
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
                    'port'    => (int) $account->proxy_port,
                    'secret'  => (string) $account->proxy_secret,
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

    private function runtimeCacheKey(MtprotoTelegramAccount $account): string
    {
        // include proxy config so changing proxy won't reuse wrong instance
        $parts = [
            (string)$account->id,
            (string)($account->proxy_type ?? 'none'),
            (string)($account->proxy_host ?? ''),
            (string)($account->proxy_port ?? ''),
            (string)($account->proxy_user ?? ''),
            (string)($account->proxy_pass ?? ''),
            (string)($account->proxy_secret ?? ''),
        ];

        return substr(sha1(implode('|', $parts)), 0, 24);
    }
}
