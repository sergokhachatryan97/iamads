<?php

namespace App\Console\Commands;

use App\Jobs\ImportProfileSeedsFromSheetJob;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

/**
 * Command to import profile seeds from Google Sheet.
 */
class TelegramSheetImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:sheet-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import profile seeds from Google Sheet (CSV or API)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!config('telegram_mtproto.sheet.enabled', false)) {
            $this->warn('Sheet import is disabled in configuration.');
            return CommandAlias::FAILURE;
        }

        $this->info('Importing profile seeds from sheet...');

        ImportProfileSeedsFromSheetJob::dispatch()->onQueue('tg-sheet-import')->afterCommit();

        $this->info('Import job queued.');

        return CommandAlias::SUCCESS;
    }
}
