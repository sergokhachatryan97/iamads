<?php

namespace App\Console\Commands;

use App\Jobs\SocpanelPollOrdersJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SocpanelPollOrders extends Command
{
    protected $signature = 'socpanel:poll';
    protected $description = 'Poll Socpanel orders and sync provider_orders';

    public function handle(): int
    {
        Log::info('Socpanel poll: dispatching jobs (queued + active)', ['trigger' => 'schedule']);

        SocpanelPollOrdersJob::dispatch('queued')
            ->onQueue('socpanel-poll');

        SocpanelPollOrdersJob::dispatch('active')
            ->onQueue('socpanel-poll')
            ->delay(now()->addSeconds(90));

        $this->info('Socpanel poll jobs dispatched.');
        return self::SUCCESS;
    }

}
