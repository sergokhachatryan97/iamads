<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramUnsubscribePhaseActivator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ActivateTelegramUnsubscribePhaseCommand extends Command
{
    protected $signature = 'telegram:activate-unsubscribing-phase';

    protected $description = 'Set Telegram orders to execution_phase unsubscribing when unsubscribe is due (completed/canceled + duration)';

    public function handle(TelegramUnsubscribePhaseActivator $activator): int
    {
        $count = $activator->activate();

        if ($count === 0) {
            $this->info('No Telegram orders activated for unsubscribing phase.');
        } else {
            $this->info("Activated unsubscribing phase for {$count} Telegram order(s).");
        }

        return CommandAlias::SUCCESS;
    }
}
