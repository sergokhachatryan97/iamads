<?php

namespace App\Services\Provider;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ProviderClient
{
    protected string $baseUrl;
    protected ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.provider.base_url', '');
        $this->apiKey = config('services.provider.api_key');
    }
    /**
     * Get headers for API requests.
     */
    protected function getHeaders(): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        return $headers;
    }


    /**
     * Extract a value from response using multiple possible keys.
     *
     * @param array $data
     * @param array<string> $possibleKeys
     * @return mixed
     */
    protected function extractValue(array $data, array $possibleKeys)
    {
        foreach ($possibleKeys as $key) {
            $value = $this->getNestedValue($data, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get nested value from array using dot notation.
     *
     * @param array $data
     * @param string $key
     * @return mixed
     */
    protected function getNestedValue(array $data, string $key)
    {
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $data;

            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return null;
                }
                $value = $value[$k];
            }

            return $value;
        }

        return $data[$key] ?? null;
    }

    /**
     * Extract error message from response.
     */
    protected function extractErrorMessage(array $rawResponse, int $statusCode): string
    {
        $errorKeys = ['message', 'error', 'error_message', 'data.message', 'data.error'];

        foreach ($errorKeys as $key) {
            $error = $this->getNestedValue($rawResponse, $key);
            if ($error) {
                return (string) $error;
            }
        }

        return "HTTP {$statusCode}";
    }


    /**
     * Normalize provider response into standard structure.
     *
     * @param array $raw
     * @param string|null $taskId Optional task_id if not in response
     * @return array{state: string, ok: bool, task_id: string|null, retry_after: int|null, error: string|null, raw: mixed}
     */
    private function normalizeProviderResponse(array $raw, ?string $taskId = null): array
    {
        // Extract state: pending | done | failed
        $state = strtolower((string) ($raw['state'] ?? 'done'));
        if (!in_array($state, ['pending', 'done', 'failed'], true)) {
            // Fallback: if ok is false, assume failed; if task_id exists, assume pending; else done
            if (isset($raw['task_id']) || $taskId !== null) {
                $state = 'pending';
            } elseif (($raw['ok'] ?? true) === false) {
                $state = 'failed';
            } else {
                $state = 'done';
            }
        }

        // Extract task_id
        $normalizedTaskId = $raw['task_id'] ?? $taskId ?? null;

        // Extract retry_after
        $retryAfter = $this->extractValue($raw, ['retry_after', 'retryAfter', 'data.retry_after']);
        if ($retryAfter !== null) {
            $retryAfter = (int) $retryAfter;
        }

        // Extract ok (for done/failed states)
        $ok = ($raw['ok'] ?? true);
        if ($state === 'pending') {
            $ok = false; // Pending is not yet successful
        } elseif ($state === 'failed') {
            $ok = false;
        }

        // Extract error
        $error = $raw['error'] ?? $raw['message'] ?? null;
        if ($state === 'failed' && !$error) {
            $error = 'Task failed';
        }

        return [
            'state' => $state,
            'ok' => (bool) $ok,
            'task_id' => $normalizedTaskId,
            'retry_after' => $retryAfter,
            'error' => $error,
            'raw' => $raw,
        ];
    }

    /**
     * Execute a single Telegram step for a quota (one unit of work).
     *
     * @param \App\Models\ClientServiceQuota $quota
     * @param \App\Models\TelegramAccount $account
     * @param string $action The action to perform (subscribe, follow, join, react, view)
     * @param array $telegramInspection Inspection result from TelegramInspector
     * @return array{ok: bool, error: string|null, raw: mixed|null, retry_after?: int}
     */
    public function executeTelegramQuotaStep(
        \App\Models\ClientServiceQuota $quota,
        \App\Models\TelegramAccount $account,
        string $action,
        array $telegramInspection
    ): array {
        $executionMeta = $quota->provider_payload['execution_meta'] ?? [];

        // Get post_id from telegramInspection (stored during inspection) for post-related actions
        $postId = null;
        if (in_array($action, ['react', 'comment', 'view'], true) && isset($telegramInspection['post_id'])) {
            $postId = (int) $telegramInspection['post_id'];
        }

        $payload = [
            'quota' => [
                'id' => $quota->id,
                'link' => $quota->link,
                'quantity_left' => $quota->quantity_left,
                'orders_left' => $quota->orders_left,
            ],
            'service' => [
                'id' => (int) $quota->service_id,
                'service_type' => $executionMeta['service_type'] ?? ($quota->service->service_type ?? null),
            ],
            'execution' => [
                'action' => $action,
                'link_type' => $executionMeta['link_type'] ?? null,
                'per_call' => 1,
                'requires_unique_account' => true,
                'unique_scope' => [
                    'quota_id' => (int) $quota->id,
                    'service_id' => (int) $quota->service_id,
                ],
            ],
            'account' => [
                'id' => (int) $account->id,
            ],
            'telegram' => [
                'inspection' => $telegramInspection,
            ],
        ];

        // Add post_id for post-related actions (react, comment, view)
        if ($postId !== null) {
            $payload['telegram']['post_id'] = $postId;
        }

        try {
            if (empty($this->baseUrl)) {
                return [
                    'ok' => false,
                    'error' => 'PROVIDER_BASE_URL_NOT_CONFIGURED',
                    'raw' => ['message' => 'Provider endpoint not configured'],
                ];
            }

            $endpoint = rtrim($this->baseUrl, '/') . '/telegram/execute-quota-step';

            $response = Http::withHeaders($this->getHeaders())
                ->timeout(30)
                ->post($endpoint, $payload);

            $raw = $response->json();

            if ($response->successful() && is_array($raw)) {
                $result = [
                    'ok' => (bool) ($raw['ok'] ?? true),
                    'error' => $raw['error'] ?? $raw['message'] ?? null,
                    'raw' => $raw,
                ];

                // Check for retry_after in response
                $retryAfter = $this->extractValue($raw, ['retry_after', 'retryAfter', 'data.retry_after']);
                if ($retryAfter !== null) {
                    $result['retry_after'] = (int) $retryAfter;
                }

                return $result;
            }

            $raw = is_array($raw) ? $raw : ['body' => (string) $response->body()];
            $errorMessage = $this->extractErrorMessage($raw, $response->status());

            return [
                'ok' => false,
                'error' => $errorMessage,
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            Log::error('Provider execute telegram quota step failed', [
                'quota_id' => $quota->id,
                'account_id' => $account->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'raw' => null,
            ];
        }
    }

    /**
     * Execute unsubscribe action for a Telegram account (async-friendly).
     *
     * @param \App\Models\TelegramAccount $account
     * @param string $linkHash
     * @param string|null $subjectType
     * @param int|null $subjectId
     * @return array{state: string, ok: bool, task_id: string|null, retry_after: int|null, error: string|null, raw: mixed}
     */
    public function executeTelegramUnsubscribe(
        \App\Models\TelegramAccount $account,
        string $linkHash,
        ?string $subjectType = null,
        ?int $subjectId = null
    ): array {
        $payload = [
            'account' => [
                'id' => (int) $account->id,
            ],
            'link_hash' => $linkHash,
            'action' => 'unsubscribe',
        ];

        if ($subjectType && $subjectId) {
            $payload['subject'] = [
                'type' => $subjectType,
                'id' => $subjectId,
            ];
        }

        try {
            if (empty($this->baseUrl)) {
                return [
                    'state' => 'failed',
                    'ok' => false,
                    'error' => 'PROVIDER_BASE_URL_NOT_CONFIGURED',
                    'task_id' => null,
                    'retry_after' => null,
                    'raw' => ['message' => 'Provider endpoint not configured'],
                ];
            }

            $endpoint = rtrim($this->baseUrl, '/') . '/telegram/unsubscribe';

            $response = Http::withHeaders($this->getHeaders())
                ->connectTimeout(5)
                ->timeout(15)
                ->post($endpoint, $payload);

            $raw = $response->json();

            if ($response->successful() && is_array($raw)) {
                return $this->normalizeProviderResponse($raw, null);
            }

            $raw = is_array($raw) ? $raw : ['body' => (string) $response->body()];
            $errorMessage = $this->extractErrorMessage($raw, $response->status());

            return [
                'state' => 'failed',
                'ok' => false,
                'error' => $errorMessage,
                'task_id' => null,
                'retry_after' => null,
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            Log::error('Provider execute telegram unsubscribe failed', [
                'account_id' => $account->id,
                'link_hash' => $linkHash,
                'error' => $e->getMessage(),
            ]);

            return [
                'state' => 'failed',
                'ok' => false,
                'error' => $e->getMessage(),
                'task_id' => null,
                'retry_after' => null,
                'raw' => null,
            ];
        }
    }

    /**
     * Publish step result to Redis Stream for high-throughput processing.
     *
     * @param string $subjectType Order or ClientServiceQuota class name
     * @param int|null $subjectId
     * @param int $accountId
     * @param string $action
     * @param string $linkHash
     * @param bool $ok
     * @param string|null $error
     * @param int $perCall
     * @param int|null $retryAfter
     * @param array|null $extra Optional extra data
     * @return void
     */
    public function publishStepResult(
        string $subjectType,
        ?int $subjectId,
        int $accountId,
        string $action,
        string $linkHash,
        bool $ok,
        ?string $error = null,
        int $perCall = 1,
        ?int $retryAfter = null,
        ?array $extra = null
    ): void {
        $streamName = config('telegram.stream.name', 'tg:step-results');

        $eventData = [
            'subject_type' => (string) $subjectType,
            'subject_id'   => $subjectId !== null ? (string) $subjectId : '',
            'account_id'   => (string) $accountId,
            'action'       => (string) $action,
            'link_hash'    => (string) $linkHash,
            'ok'           => $ok ? '1' : '0',
            'error'        => $error ? (string) $error : '',
            'per_call'     => (string) $perCall,
            'retry_after'  => $retryAfter !== null ? (string) $retryAfter : null,
            'performed_at' => (string) now()->toDateTimeString(),
            'extra'        => $extra !== null ? json_encode($extra) : '',
        ];

        try {
            $flat = [];
            foreach ($eventData as $k => $v) {
                $flat[] = (string) $k;
                $flat[] = (string) ($v ?? '');
            }

            $client = Redis::connection()->client();

            // Predis
            if (method_exists($client, 'executeRaw')) {
                $client->executeRaw(array_merge(['XADD', $streamName, '*'], $flat));
                return;
            }

            // PhpRedis (Redis / RedisCluster)
            if (method_exists($client, 'rawCommand')) {
                $client->rawCommand('XADD', $streamName, '*', ...$flat);
                return;
            }

            // Fallback (shouldn't happen often)
            Redis::connection()->command('XADD', array_merge([$streamName, '*'], $flat));
        } catch (\Throwable $e) {
            Log::error('Failed to publish step result to stream', [
                'stream' => $streamName,
                'error' => $e->getMessage(),
                'event_data' => $eventData,
            ]);
        }
    }


    /**
     * Dispatch an account action to provider.
     *
     * @param int $accountId
     * @param string $action The action to perform (session_cleanup, set_2fa_password, set_profile_name)
     * @param array $payload Action-specific payload
     * @param string|null $requestId Optional request ID for idempotency
     * @return array{state: string, ok: bool, task_id: string|null, retry_after: int|null, error: string|null, raw: mixed}
     */
    public function dispatchAccountAction(int $accountId, string $action, array $payload = [], ?string $requestId = null): array
    {
        $requestPayload = [
            'account_id' => $accountId,
            'action' => $action,
            'payload' => $payload,
        ];

        if ($requestId) {
            $requestPayload['request_id'] = $requestId;
        }

        try {
            if (empty($this->baseUrl)) {
                return [
                    'state' => 'failed',
                    'ok' => false,
                    'error' => 'PROVIDER_BASE_URL_NOT_CONFIGURED',
                    'task_id' => null,
                    'retry_after' => null,
                    'raw' => ['message' => 'Provider endpoint not configured'],
                ];
            }

            $endpoint = rtrim($this->baseUrl, '/') . '/telegram/account-action';

            $response = Http::withHeaders($this->getHeaders())
                ->connectTimeout(5)
                ->timeout(15)
                ->post($endpoint, $requestPayload);

            $raw = $response->json();

            if ($response->successful() && is_array($raw)) {
                return $this->normalizeProviderResponse($raw, null);
            }

            $raw = is_array($raw) ? $raw : ['body' => (string) $response->body()];
            $errorMessage = $this->extractErrorMessage($raw, $response->status());

            return [
                'state' => 'failed',
                'ok' => false,
                'error' => $errorMessage,
                'task_id' => null,
                'retry_after' => null,
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            Log::error('Provider dispatch account action failed', [
                'account_id' => $accountId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return [
                'state' => 'failed',
                'ok' => false,
                'error' => $e->getMessage(),
                'task_id' => null,
                'retry_after' => null,
                'raw' => null,
            ];
        }
    }
}

