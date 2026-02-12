<?php

namespace App\Jobs;

use App\Models\Mtproto2faState;
use App\Models\MtprotoTelegramAccount;
use App\Services\Telegram\AccountPasswordGenerator;
use App\Services\Telegram\MtprotoClientFactory;
use danog\MadelineProto\RPCErrorException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Enable2faJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public int $timeout = 180;

    public function __construct(public int $accountId) {}

    public function handle(
        AccountPasswordGenerator $passwordGenerator,
        MtprotoClientFactory $factory
    ): void {
        $account = MtprotoTelegramAccount::query()->find($this->accountId);
        if (!$account) {
            Log::warning('Account not found for 2FA enablement', ['account_id' => $this->accountId]);
            return;
        }

        $state = Mtproto2faState::query()->where('account_id', $account->id)->first();
        if ($state && $state->isConfirmed()) {
            Log::debug('2FA already confirmed', ['account_id' => $account->id]);
            return;
        }

        // lock
        $lockKey = "tg:mtproto:lock:{$account->id}";
        $lock = Cache::lock($lockKey, 180);

        try {
            try {
                $lock->block(1);
            } catch (LockTimeoutException $e) {
                Log::debug('MTP_LOCK_BUSY for 2FA enablement', ['account_id' => $account->id]);
                $this->release(random_int(5, 15));
                return;
            }

            $madeline = $factory->makeForRuntime($account);

            // already has 2fa?
            $passwordInfo = $madeline->account->getPassword();
//            $passwordInfo['has_password'] = false;
            if (!empty($passwordInfo['has_password'])) {
                $state = $state ?: new Mtproto2faState(['account_id' => $account->id]);
                $state->status = Mtproto2faState::STATUS_CONFIRMED;
                $state->last_error = null;
                $state->save();

                Log::info('2FA already enabled', ['account_id' => $account->id]);
                return;
            }

            $baseEmail = config('telegram_mtproto.setup.2fa.base_email');
            if (!$baseEmail) {
                throw new \RuntimeException('2FA base_email not configured');
            }

            $password = $passwordGenerator->generate();
//            $password = 'Gw1d1QFNmvdps5g1';
            $emailAlias = $this->generateEmailAlias($baseEmail, $account->id);
            $hint = (string) (config('telegram_mtproto.setup.2fa.hint') ?? '');

            // Create/update state BEFORE calling Telegram (so we never violate DB constraints)
            $state = $state ?: new Mtproto2faState();
            $state->account_id = $account->id;
            $state->setPassword($password);
            $state->email_alias = $emailAlias;
            $state->status = Mtproto2faState::STATUS_WAITING_EMAIL;
            $state->last_error = null;
            $state->save();

            // Call Telegram
            try {
                // Some versions use $madeline->update2fa, some $madeline->account->updatePassword
                if (method_exists($madeline, 'update2fa')) {
                    $madeline->update2fa([
                        'password' => '',
                        'new_password' => $password,
                        'hint' => $hint,
                        'email' => $emailAlias,
                    ]);
                }

                Log::info('2FA enable requested, waiting for email confirmation', [
                    'account_id' => $account->id,
                    'email_alias_hash' => substr(sha1($emailAlias), 0, 10),
                ]);

            } catch (RPCErrorException $e) {
                $factory->forgetRuntimeInstance($account);

                $msg = strtoupper($e->getMessage());

                // FLOOD_WAIT
                if (preg_match('/FLOOD_WAIT_(\d+)/', $msg, $m)) {
                    $waitSeconds = min(((int)$m[1]) + 3, 3600);
                    $account->setCooldown($waitSeconds);

                    $state->status = Mtproto2faState::STATUS_WAITING_EMAIL; // keep
                    $state->last_error = 'FLOOD_WAIT';
                    $state->save();

                    $this->release($waitSeconds);
                    return;
                }

                // EMAIL_UNCONFIRMED_* -> this is EXPECTED, not failure
                if (str_contains($msg, 'EMAIL_UNCONFIRMED')) {
                    $state->status = Mtproto2faState::STATUS_WAITING_EMAIL;
                    $state->last_error = 'EMAIL_UNCONFIRMED';
                    $state->save();

                    Confirm2faEmailJob::dispatch($account->id)
                        ->delay(now()->addSeconds(20))
                        ->onQueue('tg-2fa-confirm')
                        ->afterCommit();

                    return;
                }

                // unknown error
                $state->status = Mtproto2faState::STATUS_FAILED;
                $state->last_error = $e->getMessage();
                $state->save();

                throw $e;
            }

            // dispatch confirm job (normal)
            Confirm2faEmailJob::dispatch($account->id)
                ->delay(now()->addSeconds(30))
                ->onQueue('tg-2fa-confirm')
                ->afterCommit();

        } catch (\Throwable $e) {
            // mark failed but don't violate db: state might exist with password already
            $state = $state ?: new Mtproto2faState();
            $state->account_id = $account->id;
            $state->status = Mtproto2faState::STATUS_FAILED;
            $state->last_error = $e->getMessage();
            $state->save();

            Log::error('Enable2faJob failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            try { $lock->release(); } catch (\Throwable $e) {}
        }
    }

    private function generateEmailAlias(string $baseEmail, int $accountId): string
    {
        if (!preg_match('/^([^@]+)@(.+)$/', $baseEmail, $m)) {
            throw new \InvalidArgumentException("Invalid base email format: {$baseEmail}");
        }
        return "{$m[1]}+acc_{$accountId}@{$m[2]}";
    }
}
