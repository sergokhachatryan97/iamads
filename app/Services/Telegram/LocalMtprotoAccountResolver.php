<?php

namespace App\Services\Telegram;

use App\Models\MtprotoTelegramAccount;
use App\Models\TelegramAccount;

/**
 * Resolves TelegramAccount (provider/pull) to MtprotoTelegramAccount for local MadelineProto execution.
 * Uses telegram_accounts.mtproto_account_id only (no phone guessing).
 */
class LocalMtprotoAccountResolver
{
    /**
     * Resolve MtprotoTelegramAccount from TelegramAccount by FK.
     * Ensures MTProto account is active, not disabled, and optionally past cooldown.
     *
     * @param TelegramAccount $account
     * @return MtprotoTelegramAccount|null
     */
    public function resolve(TelegramAccount $account): ?MtprotoTelegramAccount
    {
        $mtprotoAccountId = $account->mtproto_account_id;
        if ($mtprotoAccountId === null) {
            return null;
        }

        $query = MtprotoTelegramAccount::query()
            ->whereKey($mtprotoAccountId)
            ->where('is_active', true)
            ->whereNull('disabled_at');

        // Optional: only return if past cooldown
        $query->where(function ($q) {
            $q->whereNull('cooldown_until')
                ->orWhere('cooldown_until', '<=', now());
        });

        return $query->first();
    }
}
