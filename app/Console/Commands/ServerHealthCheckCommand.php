<?php

namespace App\Console\Commands;

use App\Services\HealthCheck\ServerHealthCheckService;
use Illuminate\Console\Command;

class ServerHealthCheckCommand extends Command
{
    protected $signature = 'server:health-check';

    protected $description = 'Check server CPU, memory, disk and queue health; send a Telegram alert if any threshold is exceeded.';

    public function handle(ServerHealthCheckService $service): int
    {
        $results = $service->run();

        foreach ($results as $metric => $result) {
            $this->line(sprintf(
                '[%s] %s — %s',
                strtoupper($result['status']),
                $metric,
                $result['message']
            ));
        }

        return self::SUCCESS;
    }
}
