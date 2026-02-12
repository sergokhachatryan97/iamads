<?php

namespace App\Console\Commands;

use App\Models\TelegramUnsubscribeTask;
use App\Jobs\ProcessUnsubscribeTaskJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

class ProcessUnsubscribeTasksCommand extends Command
{
    protected $signature = 'telegram:process-unsubscribe-tasks {--limit=100 : Maximum tasks to process}';

    protected $description = 'Process pending unsubscribe tasks that are due';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $tasks = TelegramUnsubscribeTask::query()
            ->where('status', 'pending')
            ->where('due_at', '<=', now())
            ->orderBy('due_at', 'asc')
            ->limit($limit)
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No pending unsubscribe tasks to process.');
            return CommandAlias::SUCCESS;
        }

        $this->info("Dispatching {$tasks->count()} unsubscribe tasks...");

        foreach ($tasks as $task) {
            ProcessUnsubscribeTaskJob::dispatch($task->id);
        }

        $this->info("Dispatched {$tasks->count()} unsubscribe task jobs.");

        return CommandAlias::SUCCESS;
    }
}
