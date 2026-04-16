<?php

namespace App\Console\Commands;

use App\Support\SystemGuard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Operator kill switch. Flips the runtime cache flag that SystemGuard checks
 * on every poll/dispatch. Use during incidents to halt new work without
 * stopping Horizon itself (so in-flight jobs can still drain).
 *
 *   php artisan system:pause            # pause everything
 *   php artisan system:pause --ttl=600  # pause for 10 min, auto-resume
 *   php artisan system:resume           # resume
 *   php artisan system:status           # show current guard state
 */
class SystemPauseCommand extends Command
{
    protected $signature = 'system:pause {--ttl=0 : Auto-resume after N seconds (0 = until manually resumed)}';

    protected $description = 'Pause all guard-protected polling/dispatch until resumed';

    public function handle(): int
    {
        $ttl = (int) $this->option('ttl');
        // 0 == "forever" in practice: 30 days is plenty; ops should resume
        // manually. A non-zero TTL is useful for e.g. planned DB maintenance.
        $effectiveTtl = $ttl > 0 ? $ttl : 60 * 60 * 24 * 30;

        Cache::put('system:pause', true, $effectiveTtl);

        $this->warn('SYSTEM PAUSED — polling + heavy job dispatch are skipping.');
        $this->line('  Load threshold : ' . SystemGuard::loadThreshold());
        $this->line('  Current load   : ' . (SystemGuard::currentLoad() ?? 'n/a'));
        if ($ttl > 0) {
            $this->line("  Auto-resume in : {$ttl}s");
        } else {
            $this->line('  Auto-resume    : none (run `system:resume` to clear)');
        }

        return self::SUCCESS;
    }
}
