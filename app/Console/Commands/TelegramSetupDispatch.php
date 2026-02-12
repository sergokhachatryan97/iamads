<?php

namespace App\Console\Commands;

use App\Jobs\DispatchAccountSetupTasksJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Command to dispatch account setup tasks.
 *
 * This command creates setup tasks for eligible accounts and dispatches runner jobs.
 */
class TelegramSetupDispatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:setup-dispatch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch account setup tasks for eligible accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('telegram_mtproto.setup.enabled', false)) {
            $this->warn('Account setup is disabled in configuration.');
            return CommandAlias::FAILURE;
        }

        $this->info('Dispatching account setup tasks...');

        DispatchAccountSetupTasksJob::dispatch()->afterCommit();

        $this->info('Account setup tasks dispatch job queued.');

        return CommandAlias::SUCCESS;
    }
}
