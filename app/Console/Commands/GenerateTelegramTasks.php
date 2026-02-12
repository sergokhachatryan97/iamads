<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramTaskService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Command to generate Telegram tasks from eligible orders.
 *
 * This command can be run periodically (e.g., every minute) to generate tasks
 * for the provider pull architecture.
 */
class GenerateTelegramTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:tasks:generate {--limit=1000 : Maximum number of tasks to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Telegram tasks from eligible orders, quotas, and unsubscribe tasks for provider pull';

    /**
     * Execute the console command.
     */
    public function handle(TelegramTaskService $taskService): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Generating tasks (limit: {$limit})...");

        $generated = $taskService->generateTasks($limit);

        $this->info("Generated {$generated} tasks.");

        return CommandAlias::SUCCESS;
    }
}
