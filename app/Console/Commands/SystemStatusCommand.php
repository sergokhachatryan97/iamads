<?php

namespace App\Console\Commands;

use App\Support\SystemGuard;
use Illuminate\Console\Command;

/**
 * Diagnostic: prints the current SystemGuard state (pause flag + load) and
 * decides whether heavy work would be skipped right now.
 */
class SystemStatusCommand extends Command
{
    protected $signature = 'system:status';

    protected $description = 'Show current SystemGuard state (pause + load circuit breaker)';

    public function handle(): int
    {
        $paused = SystemGuard::isSystemPaused();
        $overload = SystemGuard::isOverloaded();
        $load = SystemGuard::currentLoad();
        $threshold = SystemGuard::loadThreshold();

        $this->line('--- SystemGuard status ---');
        $this->line(sprintf('  pause flag       : %s', $paused ? 'ON (work will skip)' : 'off'));
        $this->line(sprintf('  load threshold   : %.2f', $threshold));
        $this->line(sprintf('  current load 1m  : %s', $load === null ? 'n/a' : number_format($load, 2)));
        $this->line(sprintf('  overload guard   : %s', $overload ? 'TRIPPED (work will skip)' : 'ok'));
        $this->line(sprintf('  net effect       : %s', SystemGuard::shouldSkipHeavyWork('status-check')
            ? 'HEAVY WORK IS CURRENTLY SKIPPED'
            : 'heavy work will run normally'));

        return self::SUCCESS;
    }
}
