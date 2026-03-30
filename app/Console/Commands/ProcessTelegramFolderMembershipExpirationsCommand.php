<?php

namespace App\Console\Commands;

use App\Models\MtprotoTelegramAccount;
use App\Models\Order;
use App\Models\TelegramFolderMembership;
use App\Services\Telegram\Folder\PremiumFolderAccountUsageLimiter;
use App\Services\Telegram\Folder\TelegramFolderService;
use App\Services\Telegram\MtprotoClientFactory;
use App\Support\TelegramLinkParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessTelegramFolderMembershipExpirationsCommand extends Command
{
    protected $signature = 'telegram:process-folder-expirations {--limit=200 : Max rows per run}';

    protected $description = 'Remove Telegram peers from premium folders after duration expires';

    public function handle(
        TelegramFolderService $folderService,
        MtprotoClientFactory $mtprotoClientFactory,
        PremiumFolderAccountUsageLimiter $usageLimiter
    ): int {
        $limit = max(1, (int) $this->option('limit'));
        $processed = 0;

        TelegramFolderMembership::query()
            ->where('status', TelegramFolderMembership::STATUS_ACTIVE)
            ->where('remove_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (TelegramFolderMembership $row) use ($usageLimiter, $folderService, $mtprotoClientFactory, &$processed): void {
                $account = MtprotoTelegramAccount::query()->find($row->mtproto_telegram_account_id);
                if (! $account) {
                    $row->update([
                        'status' => TelegramFolderMembership::STATUS_FAILED,
                        'last_error' => 'MTProto account no longer exists',
                    ]);

                    return;
                }

                $parsed = TelegramLinkParser::parse($row->target_link);
                $lockKey = 'mtp:lock:'.$account->id;
                $lock = Cache::lock($lockKey, 300);

                if (! $lock->get()) {
                    return;
                }

                try {
                    $result = $folderService->removePeerFromFolder(
                        $account,
                        $row->target_link,
                        (int) $row->folder_id,
                        is_array($parsed) ? $parsed : []
                    );

                    if ($result['ok'] ?? false) {
                        $row->update([
                            'status' => TelegramFolderMembership::STATUS_REMOVED,
                            'last_error' => null,
                        ]);
                        if (($result['was_removed'] ?? false) === true) {
                            $account->decrement('subscription_count', 1);
                            if ((int) $account->subscription_count < 0) {
                                $account->update(['subscription_count' => 0]);
                            }
                            $usageLimiter->recordUnsubscribe((int) $account->id);
                        }

                        // Signal the claim system: performers should unsubscribe from this channel.
                        // GET /premium/getOrder will now return unsubscribe tasks for this order.
                        $order = $row->order;
                        if ($order && $order->execution_phase !== Order::EXECUTION_PHASE_UNSUBSCRIBING) {
                            $order->update([
                                'execution_phase' => Order::EXECUTION_PHASE_UNSUBSCRIBING,
                                'completed_at' => $order->completed_at ?? now(),
                            ]);
                        }

                        $account->recordSuccess();
                        $processed++;
                    } else {
                        $row->update([
                            'last_error' => (string) ($result['error'] ?? 'Removal failed'),
                        ]);
                    }
                } catch (\Throwable $e) {
                    $row->update(['last_error' => $e->getMessage()]);
                    Log::warning('telegram:process-folder-expirations row failed', [
                        'membership_id' => $row->id,
                        'error' => $e->getMessage(),
                    ]);
                } finally {
                    $lock->release();
                    $mtprotoClientFactory->forgetRuntimeInstance($account);
                }
            });

        if ($processed > 0) {
            $this->info("Removed {$processed} folder placement(s).");
        }

        return self::SUCCESS;
    }
}
