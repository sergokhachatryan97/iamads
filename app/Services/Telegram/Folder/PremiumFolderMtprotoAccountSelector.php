<?php

namespace App\Services\Telegram\Folder;

use App\Models\MtprotoTelegramAccount;
use App\Models\TelegramFolderMembership;
use Carbon\Carbon;

/**
 * Picks an internal MTProto account configured for premium folder automation.
 */
class PremiumFolderMtprotoAccountSelector
{
    private const FOLDER_MAX_PEERS = 200;

    public function __construct(
        private PremiumFolderAccountUsageLimiter $limiter
    ) {}

    public function select(): ?MtprotoTelegramAccount
    {
        $minLastUsedAt = Carbon::now()->subMinutes(30);

        $candidates = MtprotoTelegramAccount::query()
            ->where('is_active', true)
            ->whereNull('disabled_at')
            ->whereNotNull('premium_folder_id')
            ->whereNotNull('session_name')
            ->where('subscription_count', '<', 1000)
            ->where(function ($w): void {
                $w->whereNull('cooldown_until')
                    ->orWhere('cooldown_until', '<', now());
            })
//            ->where(function ($w) use ($minLastUsedAt): void {
//                $w->whereNull('last_used_at')
//                    ->orWhere('last_used_at', '<=', $minLastUsedAt);
//            })
            ->orderByRaw('last_used_at IS NULL DESC')
            ->orderBy('last_used_at', 'asc')
            ->orderBy('id')
            ->limit(50)
            ->get();

        foreach ($candidates as $account) {
            //            if (! $this->limiter->canSubscribe((int) $account->id)) {
            //                continue;
            //            }
            if (! $this->hasFolderCapacity($account)) {
                continue;
            }

            return $account;
        }

        return null;
    }

    private function hasFolderCapacity(MtprotoTelegramAccount $account): bool
    {
        $folderId = (int) ($account->premium_folder_id ?? 0);
        if ($folderId <= 0) {
            return false;
        }

        $activeCount = TelegramFolderMembership::query()
            ->where('mtproto_telegram_account_id', $account->id)
            ->where('folder_id', $folderId)
            ->where('status', TelegramFolderMembership::STATUS_ACTIVE)
            ->count();

        return $activeCount < self::FOLDER_MAX_PEERS;
    }
}
