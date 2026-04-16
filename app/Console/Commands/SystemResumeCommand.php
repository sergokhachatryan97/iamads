<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Clears the runtime kill-switch flag set by `system:pause`.
 * Safe to run even if the system wasn't paused.
 */
class SystemResumeCommand extends Command
{
    protected $signature = 'system:resume';

    protected $description = 'Resume polling and dispatch after a system:pause';

    public function handle(): int
    {
        Cache::forget('system:pause');
        $this->info('SYSTEM RESUMED — guard-protected work will run normally.');

        return self::SUCCESS;
    }
}
