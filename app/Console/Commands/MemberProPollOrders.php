<?php

namespace App\Console\Commands;

use App\Jobs\MemberProPollOrdersJob;
use Illuminate\Console\Command;

class MemberProPollOrders extends Command
{
    protected $signature = 'memberpro:poll';
    protected $description = 'Poll MemberPro orders and sync provider_orders';

    public function handle(): int
    {
        MemberProPollOrdersJob::dispatch('active')
            ->onQueue('memberpro-poll');

        $this->info('MemberPro poll jobs dispatched.');
        return self::SUCCESS;
    }

}
