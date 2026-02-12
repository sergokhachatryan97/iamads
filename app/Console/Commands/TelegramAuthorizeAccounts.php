<?php

namespace App\Console\Commands;

use App\Models\MtprotoTelegramAccount;
use App\Services\Telegram\MtprotoClientFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TelegramAuthorizeAccounts extends Command
{
    protected $signature = 'telegram:authorize {--id=} {--limit=1}';
    protected $description = 'Authorize MTProto sessions for mtproto_telegram_accounts (creates .madeline session files).';

    private static bool $revoltErrorHandlerSet = false;

    public function handle(MtprotoClientFactory $factory): int
    {
        $this->ensureRevoltErrorHandler();

        $id = $this->option('id');
        $limit = max(1, (int)($this->option('limit') ?: 1));

        $query = MtprotoTelegramAccount::query()
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->orderBy('id');

        if ($id) {
            $query->where('id', (int) $id);
        }

        // ✅ respect --limit
        $accounts = $query->limit($limit)->get();
        if ($accounts->isEmpty()) {
            $this->warn('No accounts found.');
            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $this->line('');
            $this->info("Authorizing account #{$account->id} (db_session_name={$account->session_name})");

            if (empty($account->phone_number)) {
                $phone = $this->ask('Enter phone number (international format, e.g. +374...)');
                $account->update(['phone_number' => $phone]);
            }

            try {
                // IMPORTANT: authorize should be one-at-a-time (recommended limit=1)
                $api = $factory->makeForAuthorize($account, useProxy:false);
                $api->start();


                $account->recordSuccess();
                $this->info("✅ Authorized OK. Session saved for {$account->session_name}.");
            } catch (\Throwable $e) {
                $account->recordFailure('AUTH_FAILED');
                $this->error("❌ Failed: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }

    private function ensureRevoltErrorHandler(): void
    {
        if (self::$revoltErrorHandlerSet) {
            return;
        }
        if (!class_exists(\Revolt\EventLoop::class)) {
            return;
        }
        try {
            \Revolt\EventLoop::setErrorHandler(function (\Throwable $e): void {
                Log::error('Revolt/EventLoop uncaught error (authorize)', [
                    'message' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
            });
            self::$revoltErrorHandlerSet = true;
        } catch (\Throwable $e) {
            Log::debug('Revolt EventLoop setErrorHandler not available', ['msg' => $e->getMessage()]);
        }
    }
}
