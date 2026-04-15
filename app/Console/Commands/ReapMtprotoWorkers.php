<?php

namespace App\Console\Commands;

use App\Services\Telegram\MtprotoSessionRegistry;
use Illuminate\Console\Command;

/**
 * Scheduled reaper: kills stale and orphaned MadelineProto IPC workers.
 *
 * Runs every minute via Laravel scheduler. Cleans up:
 * - Sessions older than 15 minutes (configurable in MtprotoSessionRegistry)
 * - Orphaned processes not tracked in the registry
 * - Workers whose parent process has died
 */
class ReapMtprotoWorkers extends Command
{
    protected $signature = 'mtproto:reap';
    protected $description = 'Reap stale and orphaned MadelineProto IPC worker processes';

    public function handle(MtprotoSessionRegistry $registry): int
    {
        $activeBefore = $registry->activeCount();
        $result = $registry->reap();

        $activeAfter = $registry->activeCount();

        $this->line(sprintf(
            'Reap complete: reaped=%d orphans=%d active=%d→%d',
            $result['reaped'],
            $result['orphans'],
            $activeBefore,
            $activeAfter
        ));

        // Also show current active sessions
        if ($this->getOutput()->isVerbose()) {
            $sessions = $registry->listActive();
            if (!empty($sessions)) {
                $rows = [];
                foreach ($sessions as $s) {
                    $rows[] = [
                        $s['session'] ?? '?',
                        $s['account_id'] ?? '?',
                        $s['pid'] ?? 0,
                        $s['age_seconds'] ?? 0,
                    ];
                }
                $this->table(['Session', 'Account', 'PID', 'Age (s)'], $rows);
            }
        }

        return 0;
    }
}
