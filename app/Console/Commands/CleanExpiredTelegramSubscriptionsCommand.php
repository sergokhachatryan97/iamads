<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramExpiredSubscriptionCleaner;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CleanExpiredTelegramSubscriptionsCommand extends Command
{
    protected $signature = 'telegram:clean-expired-subscriptions';

    protected $description = 'Mark Telegram memberships and link states as unsubscribed for completed orders whose service duration_days has elapsed';

    public function handle(TelegramExpiredSubscriptionCleaner $cleaner): int
    {
        $count = $cleaner->clean();

        if ($count === 0) {
            $this->info('No expired Telegram subscriptions to clean.');
        } else {
            $this->info("Cleaned {$count} expired Telegram subscription(s).");
        }

        return CommandAlias::SUCCESS;
    }
}
