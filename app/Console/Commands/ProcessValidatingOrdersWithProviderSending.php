<?php

namespace App\Console\Commands;

use App\Jobs\ProcessValidatingOrdersWithProviderSendingJob;
use Illuminate\Console\Command;

class ProcessValidatingOrdersWithProviderSending extends Command
{
    protected $signature = 'orders:process-validating-with-provider-sending';
    protected $description = 'Dispatch job to process orders with status=validating and provider_sending_at not null';

    public function handle(): int
    {
        ProcessValidatingOrdersWithProviderSendingJob::dispatch();

        $this->info('Job dispatched: ProcessValidatingOrdersWithProviderSendingJob');

        return self::SUCCESS;
    }
}
