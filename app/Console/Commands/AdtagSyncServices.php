<?php

namespace App\Console\Commands;

use App\Jobs\AdtagSyncServicesJob;
use App\Services\Providers\AdtagClient;
use Illuminate\Console\Command;

class AdtagSyncServices extends Command
{
    protected $signature = 'adtag:sync-services
                            {--now : Run sync synchronously instead of dispatching the job (for debugging)}';

    protected $description = 'Sync Adtag provider service list into the local services table';

    public function handle(): int
    {
        if ($this->option('now')) {
            $this->info('Running Adtag sync synchronously...');
            $client = app(AdtagClient::class);
            $job = new AdtagSyncServicesJob();
            $job->handle($client);
            $this->info('Adtag sync completed.');
            return self::SUCCESS;
        }

        AdtagSyncServicesJob::dispatch()->afterCommit();
        $this->info('Adtag sync services job dispatched.');
        return self::SUCCESS;
    }
}
