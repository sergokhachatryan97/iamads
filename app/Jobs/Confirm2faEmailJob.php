<?php

namespace App\Jobs;

use App\Models\Mtproto2faState;
use App\Models\MtprotoTelegramAccount;
use App\Services\Telegram\GmailService;
use App\Services\Telegram\MtprotoClientFactory;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job to confirm 2FA email by polling Gmail and submitting confirmation code.
 */
class Confirm2faEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // We handle retries manually via polling
    public array $backoff = [];
    public int $timeout = 180; // 3 minutes

    public function __construct(public int $accountId) {}

    public function handle(
        GmailService $gmailService,
        MtprotoClientFactory $factory
    ): void {
        $account = MtprotoTelegramAccount::query()->find($this->accountId);

        if (!$account) {
            Log::warning('Account not found for 2FA confirmation', ['account_id' => $this->accountId]);
            return;
        }

        $state = Mtproto2faState::query()->where('account_id', $account->id)->first();

        if (!$state) {
            Log::warning('2FA state not found for account', ['account_id' => $account->id]);
            return;
        }

        if ($state->isConfirmed()) {
            Log::debug('2FA already confirmed', ['account_id' => $account->id]);
            return;
        }

        if ($state->status === Mtproto2faState::STATUS_FAILED) {
            Log::debug('2FA state is failed, skipping confirmation', ['account_id' => $account->id]);
            return;
        }

        if (!$state->email_alias) {
            Log::warning('No email alias found for 2FA state', ['account_id' => $account->id]);
            return;
        }

        // Acquire per-account lock
        $lockKey = "tg:mtproto:lock:{$account->id}";
        $lock = Cache::lock($lockKey, 180);

        if (!$lock->block(1)) {
            Log::debug('MTP_LOCK_BUSY for 2FA confirmation', ['account_id' => $account->id]);
            $this->release(random_int(5, 15));
            return;
        }

        try {
            // Poll Gmail for confirmation code
            $pollTimeout = config('telegram_mtproto.setup.2fa.gmail_poll_timeout_seconds', 300);
            $pollInterval = config('telegram_mtproto.setup.2fa.gmail_poll_interval_seconds', 10);
            $startTime = time();
            $maxAgeHours = 24;

            while ((time() - $startTime) < $pollTimeout) {
                $code = $gmailService->findConfirmationCode($state->email_alias, $maxAgeHours);

                if ($code !== null) {
                    // Found code, try to confirm
                    $madeline = $factory->makeForRuntime($account);

                    try {
                        $madeline->account->confirmPasswordEmail(['code' => $code]);

                        // Success!
                        $state->status = Mtproto2faState::STATUS_CONFIRMED;
                        $state->last_error = null;
                        $state->save();

                        $account->recordSuccess();

                        Log::info('2FA email confirmed successfully', [
                            'account_id' => $account->id,
                            'email_alias_hash' => substr(sha1($state->email_alias), 0, 8),
                        ]);

                        return;

                    } catch (RPCErrorException $e) {
                        $factory->forgetRuntimeInstance($account);

                        // Handle FLOOD_WAIT
                        if (preg_match('/FLOOD_WAIT_(\d+)/', $e->getMessage(), $m)) {
                            $waitSeconds = min((int) $m[1] + 3, 3600);
                            $account->setCooldown($waitSeconds);
                            $this->release($waitSeconds);
                            return;
                        }

                        // Invalid code or other error
                        $state->last_error = $e->getMessage();
                        $state->save();

                        Log::warning('Failed to confirm 2FA email with code', [
                            'account_id' => $account->id,
                            'error' => $e->getMessage(),
                        ]);

                        // Retry after delay
                        $this->release(60);
                        return;
                    }
                }

                // Code not found yet, wait and retry
                sleep($pollInterval);
            }

            // Timeout reached, mark as failed if too many attempts
            $state->last_error = 'Gmail polling timeout - confirmation code not found';
            $state->save();

            // Retry job with backoff (up to 3 times)
            $attempts = $this->attempts();
            if ($attempts < 3) {
                $delay = 60 * ($attempts + 1); // 60s, 120s, 180s
                $this->release($delay);
            } else {
                $state->status = Mtproto2faState::STATUS_FAILED;
                $state->save();

                Log::error('2FA confirmation failed after max attempts', [
                    'account_id' => $account->id,
                    'attempts' => $attempts,
                ]);
            }

        } catch (\Throwable $e) {
            $state->last_error = $e->getMessage();
            $state->status = Mtproto2faState::STATUS_FAILED;
            $state->save();

            Log::error('Error during 2FA confirmation', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
