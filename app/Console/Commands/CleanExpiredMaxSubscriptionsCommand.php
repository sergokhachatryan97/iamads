<?php

namespace App\Console\Commands;

use App\Services\Max\MaxExpiredSubscriptionCleaner;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class CleanExpiredMaxSubscriptionsCommand extends Command
{
    protected $signature = 'max:clean-expired-subscriptions';

    protected $description = 'Delete Max subscribe tasks for completed orders whose service duration_days has elapsed';

    public function handle(MaxExpiredSubscriptionCleaner $cleaner): int
    {
        $count = $cleaner->clean();

        if ($count === 0) {
            $this->info('No expired Max subscriptions to clean.');
        } else {
            $this->info("Deleted {$count} expired Max subscription task(s).");
        }

        return CommandAlias::SUCCESS;
    }
}
