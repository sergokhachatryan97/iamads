<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramTaskWorker;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Local worker: pull/lease tasks from DB and execute via MadelineProto.
 *
 * Uses the same pull architecture (telegram_tasks + leasing + report finalization)
 * but runs inside this app with real Telegram accounts.
 */
class WorkTelegramTasks extends Command
{
    protected $signature = 'telegram:tasks:work
                            {--limit=200 : Max tasks to lease per batch}
                            {--lease=60 : Lease TTL in seconds}
                            {--once : Run one batch and exit; otherwise run until interrupted}';

    protected $description = 'Run local Telegram task worker (pull from DB, execute via MadelineProto)';

    public function handle(TelegramTaskWorker $worker): int
    {
        $limit = (int) $this->option('limit');
        $lease = (int) $this->option('lease');
        $once = (bool) $this->option('once');

        $this->info(sprintf(
            'Local Telegram task worker starting (limit=%d, lease=%ds, once=%s).',
            $limit,
            $lease,
            $once ? 'yes' : 'no'
        ));

        if ($once) {
            $stats = $worker->runBatch($limit, $lease);
            $this->printBatchStats($stats);
            return CommandAlias::SUCCESS;
        }

        while (true) {
            $stats = $worker->runBatch($limit, $lease);
            $this->printBatchStats($stats);

            if ($stats['leased'] === 0) {
                // Idle: brief sleep to avoid tight loop
                sleep(5);
            }
        }
    }

    private function printBatchStats(array $stats): void
    {
        $this->line(sprintf(
            'Batch: leased=%d ok=%d failed=%d skipped=%d',
            $stats['leased'],
            $stats['ok'],
            $stats['failed'],
            $stats['skipped']
        ));
    }
}
