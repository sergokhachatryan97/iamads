<?php

namespace App\Console\Commands;

use App\Jobs\SocpanelCancelInvalidOrderJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Dispatch job to cancel invalid provider orders on Socpanel (cursor-based).
 * Can be run via cron or manually.
 */
class SocpanelCancelInvalidOrders extends Command
{
    protected $signature = 'socpanel:cancel-invalid
                            {--sync : Run synchronously in foreground (no queue)}';

    protected $description = 'Cancel invalid Socpanel orders (invalid_link/restricted) via provider API';

    public function handle(): int
    {
        if ($this->option('sync')) {
            $this->info('Running cancel job synchronously...');
            $job = new SocpanelCancelInvalidOrderJob();
            $job->handle(app(\App\Services\Providers\SocpanelClient::class));
            $this->info('Done.');
            return CommandAlias::SUCCESS;
        }

        SocpanelCancelInvalidOrderJob::dispatch()->afterCommit();
        $this->info('Socpanel cancel invalid orders job dispatched.');
        return CommandAlias::SUCCESS;
    }
}
