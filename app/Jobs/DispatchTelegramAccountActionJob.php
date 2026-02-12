<?php

namespace App\Jobs;

use App\Models\TelegramAccount;
use App\Services\Provider\ProviderClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispatch a single account action to the provider.
 *
 * This job handles:
 * - Calling provider API with account action
 * - Tracking task_id if provider returns pending
 * - Updating account onboarding status
 * - On completion (via webhook), AccountOnboardingCompletionService will advance to next step
 */
class DispatchTelegramAccountActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public int $accountId,
        public string $action,
        public array $payload = [],
        public ?string $requestId = null
    ) {
        // Generate request_id if not provided (for idempotency)
        if (!$this->requestId) {
            $account = TelegramAccount::find($this->accountId);
            if ($account && $account->onboarding_request_seed) {
                $this->requestId = sha1($account->onboarding_request_seed . ':' . $this->action);
            } else {
                // Fallback: use account_id + action + timestamp (less ideal but works)
                $this->requestId = sha1($this->accountId . ':' . $this->action . ':' . now()->timestamp);
            }
        }
    }

    public function handle(ProviderClient $client): void
    {
        $account = TelegramAccount::find($this->accountId);

        if (!$account) {
            Log::warning('TelegramAccount not found in DispatchTelegramAccountActionJob', [
                'account_id' => $this->accountId,
            ]);
            return;
        }

        // Independent actions (rename, visibility) don't require onboarding guards
        $independentActions = ['set_profile_name', 'set_visibility'];
        $isIndependent = in_array($this->action, $independentActions, true);

        if (!$isIndependent) {
            // Verify account is in correct state (onboarding chain only)
            if (!in_array($account->onboarding_status, ['queued', 'in_progress', 'failed'], true)) {
                Log::info('Account not in eligible status for action dispatch', [
                    'account_id' => $this->accountId,
                    'status' => $account->onboarding_status,
                    'action' => $this->action,
                ]);
                return;
            }

            // Verify step matches (onboarding chain only)
            if ($account->onboarding_step !== $this->action) {
                Log::warning('Account step mismatch', [
                    'account_id' => $this->accountId,
                    'expected_step' => $account->onboarding_step,
                    'action' => $this->action,
                ]);
                return;
            }

            // Update status to in_progress for onboarding actions
            $account->update([
                'onboarding_status' => 'in_progress',
                'onboarding_last_error' => null,
            ]);
        }

        // Dispatch to provider
        $result = $client->dispatchAccountAction(
            $this->accountId,
            $this->action,
            $this->payload,
            $this->requestId
        );

        $state = $result['state'] ?? 'done';

        // Handle provider response
        if ($state === 'pending') {
            // Provider returned pending: save task_id
            $taskId = $result['task_id'] ?? null;
            if ($taskId) {
                // Only update onboarding task_id if part of onboarding flow
                if (!$isIndependent) {
                    $account->update([
                        'onboarding_last_task_id' => $taskId,
                    ]);
                }

                Log::info('Account action dispatched, waiting for provider completion', [
                    'account_id' => $this->accountId,
                    'action' => $this->action,
                    'task_id' => $taskId,
                    'request_id' => $this->requestId,
                ]);
            } else {
                Log::error('Provider returned pending but no task_id', [
                    'account_id' => $this->accountId,
                    'action' => $this->action,
                    'result' => $result,
                ]);
                // Treat as failed
                $this->handleFailure($account, 'Provider returned pending without task_id', $isIndependent);
            }
        } elseif ($state === 'failed') {
            // Immediate failure
            $errorMessage = $result['error'] ?? 'Provider action failed';
            $this->handleFailure($account, $errorMessage, $isIndependent);
        } else {
            // state === 'done' (synchronous completion)
            $this->handleSuccess($account, $result, $isIndependent);
        }
    }

    /**
     * Handle successful completion (synchronous).
     */
    private function handleSuccess(TelegramAccount $account, array $result, bool $isIndependent): void
    {
        // For independent actions, just update account state
        if ($isIndependent) {
            if ($this->action === 'set_profile_name') {
                $account->markProfileNameChanged();
            } elseif ($this->action === 'set_visibility') {
                $isVisible = $this->payload['is_visible'] ?? true;
                $account->update(['is_visible' => $isVisible]);
            }
            Log::info('Independent account action completed', [
                'account_id' => $this->accountId,
                'action' => $this->action,
            ]);
            return;
        }

        Log::info('Account action completed synchronously', [
            'account_id' => $this->accountId,
            'action' => $this->action,
        ]);

        // Use completion service to advance to next step (onboarding only)
        $completionService = app(\App\Services\Telegram\AccountOnboardingCompletionService::class);
        $completionService->handleCompletion(
            $this->accountId,
            $this->action,
            'done',
            (bool) ($result['ok'] ?? true),
            $result['error'] ?? null,
            $result['task_id'] ?? null
        );
    }

    /**
     * Handle failure.
     */
    private function handleFailure(TelegramAccount $account, string $errorMessage, bool $isIndependent): void
    {
        if (!$isIndependent) {
            $account->update([
                'onboarding_status' => 'failed',
                'onboarding_last_error' => $errorMessage,
            ]);
        }

        Log::warning('Account action failed', [
            'account_id' => $this->accountId,
            'action' => $this->action,
            'error' => $errorMessage,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $account = TelegramAccount::find($this->accountId);

        if ($account) {
            $account->update([
                'onboarding_status' => 'failed',
                'onboarding_last_error' => 'Job failed: ' . $exception->getMessage(),
            ]);
        }

        Log::error('DispatchTelegramAccountActionJob failed', [
            'account_id' => $this->accountId,
            'action' => $this->action,
            'exception' => $exception->getMessage(),
        ]);
    }
}
