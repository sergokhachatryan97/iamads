<?php

namespace App\Console\Commands;

use App\Jobs\DispatchSeedMediaDownloadsJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Command to dispatch media downloads for profile seeds.
 */
class TelegramSheetMediaDownload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:sheet-media-download';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch media downloads for profile seeds that need them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Dispatching media downloads...');

        DispatchSeedMediaDownloadsJob::dispatch()->onQueue('tg-media-prep')->afterCommit();

        $this->info('Media download dispatcher job queued.');
        $this->comment('Note: Run queue worker with low concurrency for tg-media-prep queue.');

        return CommandAlias::SUCCESS;
    }
}
