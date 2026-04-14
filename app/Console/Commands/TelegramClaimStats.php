<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TelegramClaimStats extends Command
{
    protected $signature = 'telegram:claim-stats';
    protected $description = 'Live claim statistics: requests/sec, accounts, cooldowns, per order/link';

    public function handle(): int
    {
        $this->info('Collecting claim stats...');
        $this->newLine();

        // ── 1. Requests per hour (from HyperLogLog) ──
        $currentHour = now()->format('Y-m-d-H');
        $prevHour = now()->subHour()->format('Y-m-d-H');

        $claimKeys = Redis::keys('tg:claim_attempts:*:' . $currentHour);
        $prevKeys = Redis::keys('tg:claim_attempts:*:' . $prevHour);

        $this->info('═══ Unique Accounts per Service (HyperLogLog) ═══');
        $rows = [];
        $allKeys = array_unique(array_merge($claimKeys, $prevKeys));
        $serviceIds = [];
        foreach ($allKeys as $key) {
            // key format: tg:claim_attempts:{serviceId}:{hour}
            $parts = explode(':', $key);
            if (count($parts) >= 4) {
                $serviceIds[] = $parts[2];
            }
        }
        $serviceIds = array_unique($serviceIds);
        sort($serviceIds);

        foreach ($serviceIds as $sid) {
            $current = (int) Redis::pfcount("tg:claim_attempts:{$sid}:{$currentHour}");
            $prev = (int) Redis::pfcount("tg:claim_attempts:{$sid}:{$prevHour}");
            $rows[] = [$sid, $current, $prev];
        }
        $this->table(['Service ID', 'Accounts (this hour)', 'Accounts (prev hour)'], $rows);

        // ── 2. Redis queue depths ──
        $this->newLine();
        $this->info('═══ Redis Queue Depths (push-model) ═══');
        $queueKeys = Redis::keys('tg:service_queue:*');
        $queueRows = [];
        foreach ($queueKeys as $key) {
            $len = (int) Redis::llen($key);
            if ($len > 0) {
                $queueRows[] = [$key, $len];
            }
        }
        if (empty($queueRows)) {
            $this->warn('  All queues empty — accounts are using DB fallback path');
        } else {
            $this->table(['Queue Key', 'Length'], $queueRows);
        }

        // ── 3. No-work keys (services with no tasks) ──
        $this->newLine();
        $this->info('═══ No-Work Cache (idle services) ═══');
        $noWorkKeys = Redis::keys('tg:no_work:*');
        if (empty($noWorkKeys)) {
            $this->line('  None — all services have work available');
        } else {
            foreach ($noWorkKeys as $key) {
                $ttl = Redis::ttl($key);
                $this->line("  {$key}  (TTL: {$ttl}s)");
            }
        }

        // ── 4. Tasks created last 5 min (claim throughput) ──
        $this->newLine();
        $this->info('═══ Claim Throughput (last 5 min) ═══');
        $taskStats = DB::table('telegram_tasks')
            ->join('orders', 'orders.id', '=', 'telegram_tasks.order_id')
            ->where('telegram_tasks.created_at', '>=', now()->subMinutes(5))
            ->selectRaw('orders.service_id, COUNT(*) as tasks, COUNT(DISTINCT telegram_tasks.telegram_account_id) as accounts')
            ->groupBy('orders.service_id')
            ->get();

        $throughputRows = [];
        $totalTasks = 0;
        foreach ($taskStats as $row) {
            $perSec = round($row->tasks / 300, 1);
            $throughputRows[] = [$row->service_id, $row->tasks, $perSec, $row->accounts];
            $totalTasks += $row->tasks;
        }
        $this->table(['Service ID', 'Tasks (5min)', 'Tasks/sec', 'Unique Accounts'], $throughputRows);
        $this->line("  Total: {$totalTasks} tasks (" . round($totalTasks / 300, 1) . '/sec)');

        // ── 5. Per order/link breakdown (top 15 active) ──
        $this->newLine();
        $this->info('═══ Top 15 Active Orders (last 5 min) ═══');
        $orderStats = DB::table('telegram_tasks')
            ->join('orders', 'orders.id', '=', 'telegram_tasks.order_id')
            ->where('telegram_tasks.created_at', '>=', now()->subMinutes(5))
            ->selectRaw('orders.id as order_id, orders.service_id, SUBSTRING(orders.link, 1, 50) as link, COUNT(*) as tasks, COUNT(DISTINCT telegram_tasks.telegram_account_id) as accounts')
            ->groupBy('orders.id', 'orders.service_id', DB::raw('SUBSTRING(orders.link, 1, 50)'))
            ->orderByDesc('tasks')
            ->limit(15)
            ->get();

        $orderRows = [];
        foreach ($orderStats as $row) {
            $perSec = round($row->tasks / 300, 1);
            $orderRows[] = [$row->order_id, $row->service_id, $row->link, $row->tasks, $perSec, $row->accounts];
        }
        $this->table(['Order', 'Service', 'Link', 'Tasks (5min)', '/sec', 'Accounts'], $orderRows);

        // ── 6. Cooldown sample (active cooldowns) ──
        $this->newLine();
        $this->info('═══ Active Cooldowns (sample) ═══');
        $cooldownKeys = Redis::keys('tg:phone:cooldown:subscribe:*');
        $cooldownCount = count($cooldownKeys);
        $this->line("  subscribe cooldowns active: {$cooldownCount}");

        if ($cooldownCount > 0) {
            $sample = array_slice($cooldownKeys, 0, 10);
            $cooldownRows = [];
            foreach ($sample as $key) {
                $ttl = Redis::ttl($key);
                $phone = str_replace('tg:phone:cooldown:subscribe:', '', $key);
                $cooldownRows[] = [substr($phone, 0, 6) . '****', "{$ttl}s"];
            }
            $this->table(['Account (masked)', 'Remaining'], $cooldownRows);
        }

        // ── 7. Daily cap usage (sample) ──
        $this->newLine();
        $this->info('═══ Daily Cap Usage (sample from top accounts) ═══');
        $date = now()->format('Y-m-d');
        $capKeys = Redis::keys("tg:phone:cap:default:subscribe:*:{$date}");
        $capCount = count($capKeys);
        $this->line("  Accounts with subscribe cap today: {$capCount}");

        if ($capCount > 0) {
            // Sort by usage desc, show top 10
            $caps = [];
            foreach ($capKeys as $key) {
                $val = (int) Redis::get($key);
                $caps[$key] = $val;
            }
            arsort($caps);
            $capRows = [];
            foreach (array_slice($caps, 0, 10, true) as $key => $val) {
                preg_match('/subscribe:(.+):' . preg_quote($date) . '$/', $key, $m);
                $phone = $m[1] ?? '?';
                $capRows[] = [substr($phone, 0, 6) . '****', $val, 15];
            }
            $this->table(['Account (masked)', 'Used', 'Daily Cap'], $capRows);
        }

        return 0;
    }
}
