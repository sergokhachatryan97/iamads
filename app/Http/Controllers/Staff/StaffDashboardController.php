<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\MaxTask;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramOrderMembership;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class StaffDashboardController extends Controller
{
    private const CACHE_TTL_SLOW = 300;
    private const CACHE_TTL_LIVE = 45;

    public function index(Request $request): View
    {
        $period = in_array($request->input('period'), ['days', 'monthly'], true)
            ? $request->input('period')
            : 'days';

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $hasDateFilter = $dateFrom || $dateTo;
        $dateFromParsed = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $dateToParsed = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;
        $serviceId = $request->input('service_id');
        $network = $request->input('network', 'all');

        if (! in_array($network, ['all', 'telegram', 'max'], true)) {
            $network = 'all';
        }

        [$telegramCatIds, $maxCatIds, $allServices] = $this->loadServicesAndCategories();

        $telegramServiceIds = $allServices->filter(fn ($s) => in_array($s->category_id, $telegramCatIds))->pluck('id')->all();
        $maxServiceIds = $allServices->filter(fn ($s) => in_array($s->category_id, $maxCatIds))->pluck('id')->all();

        [$filteredServices, $activeServiceIds] = match ($network) {
            'telegram' => [$allServices->filter(fn ($s) => in_array($s->category_id, $telegramCatIds)), $telegramServiceIds],
            'max' => [$allServices->filter(fn ($s) => in_array($s->category_id, $maxCatIds)), $maxServiceIds],
            default => [$allServices, array_merge($telegramServiceIds, $maxServiceIds)],
        };

        if ($serviceId && in_array((int) $serviceId, $activeServiceIds)) {
            $activeServiceIds = [(int) $serviceId];
        }

        $activeTgIds = array_values(array_intersect($activeServiceIds, $telegramServiceIds));
        $activeMaxIds = array_values(array_intersect($activeServiceIds, $maxServiceIds));

        $todayEnd = now()->endOfDay();
        [$chartLabels, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod] =
            $this->resolveChartRange($hasDateFilter, $dateFromParsed, $dateToParsed, $period, $todayEnd);

        $rateMap = $allServices->pluck('rate_per_1000', 'id');

        $keyPayload = compact('network', 'dateFrom', 'dateTo', 'serviceId', 'period');
        $slowKey = 'dash:slow:' . md5(json_encode($keyPayload));
        $liveKey = 'dash:live:' . md5(json_encode($keyPayload));

        // ── Slow block ──
        $slowBlock = Cache::remember($slowKey, self::CACHE_TTL_SLOW, function () use (
            $activeTgIds, $activeMaxIds, $rateMap,
            $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod, $hasDateFilter
        ) {
            $todayStr = now()->startOfDay()->format('Y-m-d');
            $yesterdayStr = now()->subDay()->format('Y-m-d');

            // ── Completions by purpose (optimized: no heavy joins) ──
            $tgByPeriod = $this->getTgCompletionsByPurpose($activeTgIds, $rangeStart, $rangeEnd, $groupExpr);
            $maxByPeriod = $this->getMaxCompletionsByPurpose($activeMaxIds, $rangeStart, $rangeEnd, $groupExpr);

            // Build chart rows + refill data
            $chartRows = collect();
            $refillCompletions = collect();
            $allPeriods = $tgByPeriod->keys()->merge($maxByPeriod->keys())->unique();

            foreach ($allPeriods as $p) {
                $tg = $tgByPeriod[$p] ?? null;
                $mx = $maxByPeriod[$p] ?? null;

                $tgNormal = $tg ? $tg->sum('normal') : 0;
                $mxNormal = $mx ? $mx->sum('normal') : 0;
                $tgIncome = $tg ? $tg->sum(fn ($r) => $r->normal * (float) ($rateMap[$r->service_id] ?? 0) / 1000) : 0;
                $mxIncome = $mx ? $mx->sum(fn ($r) => $r->normal * (float) ($rateMap[$r->service_id] ?? 0) / 1000) : 0;

                $chartRows[$p] = (object) [
                    'completions' => $tgNormal + $mxNormal,
                    'income' => $tgIncome + $mxIncome,
                    'tg_completions' => $tgNormal,
                    'max_completions' => $mxNormal,
                ];

                $refillTotal = ($tg ? $tg->sum('refill') : 0) + ($mx ? $mx->sum('refill') : 0);
                if ($refillTotal > 0) {
                    $refillCompletions[$p] = $refillTotal;
                }
            }

            [$incomeToday, $incomeYesterday, $completionsToday, $completionsYesterday] =
                $this->resolveTopMetrics($hasDateFilter, $resolvedPeriod, $chartRows, $todayStr, $yesterdayStr);

            // ── Daily payments (1 query) ──
            $paymentsGroupExpr = str_replace('m.subscribed_at', 'p.paid_at', $groupExpr);
            $dailyPayments = DB::table('payments as p')
                ->where('p.status', 'paid')
                ->whereNotNull('p.paid_at')
                ->whereBetween('p.paid_at', [$rangeStart, $rangeEnd])
                ->selectRaw("{$paymentsGroupExpr} as period, SUM(p.amount) as total_amount")
                ->groupByRaw($paymentsGroupExpr)
                ->pluck('total_amount', 'period');

            // ── Daily new users (1 query) ──
            $usersGroupExpr = str_replace('m.subscribed_at', 'c.created_at', $groupExpr);
            $dailyNewUsers = DB::table('clients as c')
                ->whereBetween('c.created_at', [$rangeStart, $rangeEnd])
                ->selectRaw("{$usersGroupExpr} as period, COUNT(*) as total")
                ->groupByRaw($usersGroupExpr)
                ->pluck('total', 'period');

            // ── Daily test money (1 query) ──
            $testGroupExpr = str_replace('m.subscribed_at', 'o.created_at', $groupExpr);
            $dailyTestMoney = DB::table('orders as o')
                ->where('o.order_purpose', Order::PURPOSE_TEST)
                ->whereBetween('o.created_at', [$rangeStart, $rangeEnd])
                ->whereIn('o.status', [Order::STATUS_COMPLETED, Order::STATUS_IN_PROGRESS, Order::STATUS_PARTIAL])
                ->selectRaw("{$testGroupExpr} as period, SUM(CAST(o.charge AS DECIMAL(20,4))) as total_charge")
                ->groupByRaw($testGroupExpr)
                ->pluck('total_charge', 'period');

            // ── Admin stats (PHP-side aggregation, no 3-way joins) ──
            $adminStats = $this->getAdminStats($rangeStart, $rangeEnd, $activeTgIds, $activeMaxIds);

            // ── Service order stats (1 query) ──
            $serviceStats = $this->getServiceStats(array_merge($activeTgIds, $activeMaxIds));

            return compact(
                'chartRows', 'incomeToday', 'incomeYesterday',
                'completionsToday', 'completionsYesterday',
                'dailyPayments', 'dailyNewUsers', 'refillCompletions',
                'dailyTestMoney', 'adminStats', 'serviceStats'
            );
        });

        $chartRows = $slowBlock['chartRows'];
        unset($slowBlock['chartRows']);

        // ── Live block ──
        $liveBlock = Cache::remember($liveKey, self::CACHE_TTL_LIVE, function () use (
            $hasDateFilter, $dateFromParsed, $dateToParsed, $activeTgIds, $activeMaxIds
        ) {
            return $this->buildLiveBlock($hasDateFilter, $dateFromParsed, $dateToParsed, $activeTgIds, $activeMaxIds);
        });

        $chartData = $this->buildChartArrays($chartLabels, $chartRows, $slowBlock);

        $completionsColumnLabel = $hasDateFilter ? __('Completions') : __('Completions today');

        $displayedServices = $filteredServices;
        if ($serviceId && in_array((int) $serviceId, array_merge($telegramServiceIds, $maxServiceIds))) {
            $displayedServices = $filteredServices->where('id', (int) $serviceId)->values();
        }

        return view('staff.dashboard', array_merge($slowBlock, $liveBlock, $chartData, [
            'chartLabels' => $chartLabels,
            'period' => $resolvedPeriod,
            'network' => $network,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'hasDateFilter' => $hasDateFilter,
            'serviceId' => $serviceId,
            'allServices' => $filteredServices,
            'displayedServices' => $displayedServices,
            'telegramServiceIds' => $telegramServiceIds,
            'maxServiceIds' => $maxServiceIds,
            'completionsColumnLabel' => $completionsColumnLabel,
        ]));
    }

    // ──────────────────────────────────────────────────────────────────────
    //  TG completions: 2-pass approach (no expensive full-table JOIN)
    //
    //  Pass 1: COUNT all completions — no join, same speed as original controller
    //  Pass 2: COUNT only refill+test completions — subquery on order_id
    //          keeps the scan small because refill/test orders are few
    //  Clean = total − refill − test
    // ──────────────────────────────────────────────────────────────────────

    private function getTgCompletionsByPurpose(array $tgIds, Carbon $start, Carbon $end, string $groupExpr): \Illuminate\Support\Collection
    {
        if (empty($tgIds)) {
            return collect();
        }

        // Pass 1 — total completions (NO join, fast)
        $allRows = DB::table('telegram_order_memberships as m')
            ->whereIn('m.service_id', $tgIds)
            ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNotNull('m.subscribed_at')
            ->whereBetween('m.subscribed_at', [$start, $end])
            ->selectRaw("{$groupExpr} as period, m.service_id, COUNT(*) as completions")
            ->groupByRaw("{$groupExpr}, m.service_id")
            ->get()
            ->groupBy('period');

        // Pass 2 — refill+test only (subquery keeps scan small)
        $refillIds = DB::table('orders')
            ->whereIn('service_id', $tgIds)
            ->where('order_purpose', Order::PURPOSE_REFILL)
            ->pluck('id')->all();

        $testIds = DB::table('orders')
            ->whereIn('service_id', $tgIds)
            ->where('order_purpose', Order::PURPOSE_TEST)
            ->pluck('id')->all();

        $rtIds = array_merge($refillIds, $testIds);

        if (empty($rtIds)) {
            // No refill/test — all completions are clean
            return $allRows->map(fn ($rows) => $rows->map(fn ($r) => (object) [
                'service_id' => $r->service_id,
                'normal' => (int) $r->completions,
                'refill' => 0,
                'test' => 0,
            ]));
        }

        // Count refill+test completions per period+service, using order_id IN (small set)
        $rtCompletions = DB::table('telegram_order_memberships as m')
            ->whereIn('m.order_id', $rtIds)
            ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNotNull('m.subscribed_at')
            ->whereBetween('m.subscribed_at', [$start, $end])
            ->selectRaw("{$groupExpr} as period, m.service_id, m.order_id, COUNT(*) as cnt")
            ->groupByRaw("{$groupExpr}, m.service_id, m.order_id")
            ->get();

        // Build a lookup: refill order IDs set (for O(1) check)
        $refillIdSet = array_flip($refillIds);

        // Aggregate refill/test per period+service in PHP
        $rtByPeriodService = [];
        foreach ($rtCompletions as $row) {
            $key = $row->period . '|' . $row->service_id;
            if (! isset($rtByPeriodService[$key])) {
                $rtByPeriodService[$key] = ['refill' => 0, 'test' => 0];
            }
            if (isset($refillIdSet[$row->order_id])) {
                $rtByPeriodService[$key]['refill'] += (int) $row->cnt;
            } else {
                $rtByPeriodService[$key]['test'] += (int) $row->cnt;
            }
        }

        // Merge: normal = total − refill − test
        return $allRows->map(function ($rows, $period) use ($rtByPeriodService) {
            return $rows->map(function ($r) use ($period, $rtByPeriodService) {
                $key = $period . '|' . $r->service_id;
                $rt = $rtByPeriodService[$key] ?? ['refill' => 0, 'test' => 0];

                return (object) [
                    'service_id' => $r->service_id,
                    'normal' => max(0, (int) $r->completions - $rt['refill'] - $rt['test']),
                    'refill' => $rt['refill'],
                    'test' => $rt['test'],
                ];
            });
        });
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Max completions: JOIN is cheap here (max_tasks has no service_id,
    //  must go through orders). Table is typically much smaller than TG.
    // ──────────────────────────────────────────────────────────────────────

    private function getMaxCompletionsByPurpose(array $maxIds, Carbon $start, Carbon $end, string $groupExpr): \Illuminate\Support\Collection
    {
        if (empty($maxIds)) {
            return collect();
        }

        $maxGroupExpr = str_replace('m.subscribed_at', 'mt.updated_at', $groupExpr);

        return DB::table('max_tasks as mt')
            ->join('orders as o', 'o.id', '=', 'mt.order_id')
            ->whereIn('o.service_id', $maxIds)
            ->where('mt.status', MaxTask::STATUS_DONE)
            ->whereBetween('mt.updated_at', [$start, $end])
            ->selectRaw("
                {$maxGroupExpr} as period,
                o.service_id,
                SUM(CASE WHEN o.order_purpose = ? OR o.order_purpose IS NULL THEN 1 ELSE 0 END) as normal,
                SUM(CASE WHEN o.order_purpose = ? THEN 1 ELSE 0 END) as refill,
                SUM(CASE WHEN o.order_purpose = ? THEN 1 ELSE 0 END) as test
            ", [Order::PURPOSE_NORMAL, Order::PURPOSE_REFILL, Order::PURPOSE_TEST])
            ->groupByRaw("{$maxGroupExpr}, o.service_id")
            ->get()
            ->groupBy('period');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Admin stats — PHP-side aggregation to avoid 3-way SQL joins
    //
    //  1. Get completions per order_id from TG memberships (no join)
    //  2. Batch-lookup order→client_id + purpose from orders (PK lookup)
    //  3. Batch-lookup client→staff_id from clients (PK lookup)
    //  4. Aggregate in PHP — O(n) where n = distinct orders with completions
    //
    //  For Max: JOIN orders is required (no service_id on max_tasks),
    //  but table is small so it's fast.
    // ──────────────────────────────────────────────────────────────────────

    private function getAdminStats(Carbon $rangeStart, Carbon $rangeEnd, array $tgIds, array $maxIds): \Illuminate\Support\Collection
    {
        if (empty($tgIds) && empty($maxIds)) {
            return collect();
        }

        // Staff users who have assigned clients
        $staffUsers = DB::table('users')
            ->whereIn('id', fn ($q) => $q->select('staff_id')->from('clients')->whereNotNull('staff_id')->distinct())
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        if ($staffUsers->isEmpty()) {
            return collect();
        }

        $staffIds = $staffUsers->pluck('id')->all();

        // Client → staff mapping (only clients assigned to staff)
        $clientStaffMap = DB::table('clients')
            ->whereIn('staff_id', $staffIds)
            ->pluck('staff_id', 'id');

        // Initialize per-admin accumulators
        $adminData = [];
        foreach ($staffIds as $sid) {
            $adminData[$sid] = ['normal' => 0, 'refill' => 0, 'test' => 0];
        }

        // ── TG: completions per order_id (no join, fast) ──
        if (! empty($tgIds)) {
            $tgByOrder = DB::table('telegram_order_memberships as m')
                ->whereIn('m.service_id', $tgIds)
                ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('m.subscribed_at')
                ->whereBetween('m.subscribed_at', [$rangeStart, $rangeEnd])
                ->selectRaw('m.order_id, COUNT(*) as cnt')
                ->groupBy('m.order_id')
                ->get();

            if ($tgByOrder->isNotEmpty()) {
                // Batch-lookup order details (PK lookups)
                $orderDetails = DB::table('orders')
                    ->whereIn('id', $tgByOrder->pluck('order_id'))
                    ->select('id', 'client_id', 'order_purpose')
                    ->get()
                    ->keyBy('id');

                foreach ($tgByOrder as $row) {
                    $order = $orderDetails[$row->order_id] ?? null;
                    if (! $order) continue;
                    $staffId = $clientStaffMap[$order->client_id] ?? null;
                    if (! $staffId) continue;

                    $purpose = $order->order_purpose ?: Order::PURPOSE_NORMAL;
                    $key = in_array($purpose, [Order::PURPOSE_REFILL, Order::PURPOSE_TEST]) ? $purpose : 'normal';
                    $adminData[$staffId][$key] += (int) $row->cnt;
                }
            }
        }

        // ── Max: completions per order (join orders — table is small) ──
        if (! empty($maxIds)) {
            $maxByOrder = DB::table('max_tasks as mt')
                ->join('orders as o', 'o.id', '=', 'mt.order_id')
                ->whereIn('o.service_id', $maxIds)
                ->where('mt.status', MaxTask::STATUS_DONE)
                ->whereBetween('mt.updated_at', [$rangeStart, $rangeEnd])
                ->selectRaw('o.client_id, o.order_purpose, COUNT(*) as cnt')
                ->groupBy('o.client_id', 'o.order_purpose')
                ->get();

            foreach ($maxByOrder as $row) {
                $staffId = $clientStaffMap[$row->client_id] ?? null;
                if (! $staffId) continue;

                $purpose = $row->order_purpose ?: Order::PURPOSE_NORMAL;
                $key = in_array($purpose, [Order::PURPOSE_REFILL, Order::PURPOSE_TEST]) ? $purpose : 'normal';
                $adminData[$staffId][$key] += (int) $row->cnt;
            }
        }

        // ── Admin payments (1 query) ──
        $adminPayments = DB::table('payments as p')
            ->join('clients as c', 'c.id', '=', 'p.client_id')
            ->where('p.status', 'paid')
            ->whereNotNull('p.paid_at')
            ->whereBetween('p.paid_at', [$rangeStart, $rangeEnd])
            ->whereIn('c.staff_id', $staffIds)
            ->selectRaw('c.staff_id, SUM(p.amount) as total_payments')
            ->groupBy('c.staff_id')
            ->pluck('total_payments', 'staff_id');

        // ── Admin new users (1 query) ──
        $adminNewUsers = DB::table('clients')
            ->whereIn('staff_id', $staffIds)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->selectRaw('staff_id, COUNT(*) as new_users')
            ->groupBy('staff_id')
            ->pluck('new_users', 'staff_id');

        return $staffUsers->map(function ($user) use ($adminData, $adminPayments, $adminNewUsers) {
            $d = $adminData[$user->id] ?? ['normal' => 0, 'refill' => 0, 'test' => 0];

            return (object) [
                'name' => $user->name,
                'completions' => $d['normal'],
                'payments' => (float) ($adminPayments[$user->id] ?? 0),
                'refills' => $d['refill'],
                'tests' => $d['test'],
                'new_users' => (int) ($adminNewUsers[$user->id] ?? 0),
            ];
        })->sortByDesc('completions')->values();
    }

    private function getServiceStats(array $serviceIds): \Illuminate\Support\Collection
    {
        if (empty($serviceIds)) {
            return collect();
        }

        return DB::table('orders')
            ->whereIn('service_id', $serviceIds)
            ->whereIn('status', [Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED])
            ->selectRaw('
                service_id,
                COUNT(*) as total_orders,
                COALESCE(SUM(CASE WHEN status = ? AND COALESCE(quantity, 0) > COALESCE(delivered, 0) THEN COALESCE(quantity, 0) - COALESCE(delivered, 0) ELSE 0 END), 0) as total_volume,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as in_progress_orders,
                COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed_orders,
                COALESCE(SUM(CASE WHEN status = ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END), 0) as completed_balance
            ', [Order::STATUS_IN_PROGRESS, Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED, Order::STATUS_COMPLETED])
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Live block
    // ──────────────────────────────────────────────────────────────────────

    private function buildLiveBlock(
        bool $hasDateFilter, ?Carbon $dateFromParsed, ?Carbon $dateToParsed,
        array $activeTgIds, array $activeMaxIds
    ): array {
        $today = now()->startOfDay();
        $completionsTodayPerService = collect();
        $accountsPerService = collect();
        $delayPerService = collect();

        if (! empty($activeTgIds)) {
            $tgQuery = DB::table('telegram_tasks as t')
                ->whereIn('t.service_id', $activeTgIds)
                ->whereIn('t.status', ['done', 'unsubscribed']);

            if ($hasDateFilter) {
                if ($dateFromParsed) $tgQuery->where('t.updated_at', '>=', $dateFromParsed);
                if ($dateToParsed) $tgQuery->where('t.updated_at', '<=', $dateToParsed);
            } else {
                $tgQuery->where('t.updated_at', '>=', $today);
            }

            foreach ($tgQuery->selectRaw('t.service_id, COUNT(*) as cnt')->groupBy('t.service_id')->get() as $row) {
                $completionsTodayPerService[$row->service_id] = (int) $row->cnt;
            }

            // Redis active accounts + delay
            $currentHour = now()->format('Y-m-d-H');
            $prevHour = now()->subHour()->format('Y-m-d-H');
            try {
                $results = Redis::pipeline(function ($pipe) use ($activeTgIds, $currentHour, $prevHour) {
                    foreach ($activeTgIds as $sid) {
                        $pipe->pfcount("tg:claim_attempts:{$sid}:{$currentHour}");
                        $pipe->pfcount("tg:claim_attempts:{$sid}:{$prevHour}");
                    }
                });
                foreach ($activeTgIds as $i => $sid) {
                    $count = max((int) ($results[$i * 2] ?? 0), (int) ($results[$i * 2 + 1] ?? 0));
                    if ($count > 0) $accountsPerService[$sid] = $count;
                }
            } catch (\Throwable) {}

            foreach (
                DB::table('telegram_tasks')
                    ->whereIn('service_id', $activeTgIds)
                    ->where('created_at', '>=', now()->subHour())
                    ->selectRaw('service_id, COUNT(*) as task_count')
                    ->groupBy('service_id')
                    ->pluck('task_count', 'service_id') as $sid => $count
            ) {
                if ($count > 1) $delayPerService[$sid] = round(3600 / $count);
            }
        }

        if (! empty($activeMaxIds)) {
            $maxQuery = DB::table('max_tasks as mt')
                ->join('orders as o', 'o.id', '=', 'mt.order_id')
                ->whereIn('o.service_id', $activeMaxIds)
                ->where('mt.status', MaxTask::STATUS_DONE);

            if ($hasDateFilter) {
                if ($dateFromParsed) $maxQuery->where('mt.updated_at', '>=', $dateFromParsed);
                if ($dateToParsed) $maxQuery->where('mt.updated_at', '<=', $dateToParsed);
            } else {
                $maxQuery->where('mt.updated_at', '>=', $today);
            }

            foreach ($maxQuery->selectRaw('o.service_id, COUNT(*) as cnt')->groupBy('o.service_id')->get() as $row) {
                $completionsTodayPerService[$row->service_id] = ($completionsTodayPerService[$row->service_id] ?? 0) + (int) $row->cnt;
            }
        }

        return compact('completionsTodayPerService', 'accountsPerService', 'delayPerService');
    }

    // ──────────────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────────────

    private function loadServicesAndCategories(): array
    {
        $telegramCatIds = Cache::remember('dash:tg_cat_ids', 300, fn () =>
            Category::where('link_driver', 'telegram')->pluck('id')->all()
        );
        $maxCatIds = Cache::remember('dash:max_cat_ids', 300, fn () =>
            Category::where('link_driver', 'max')->pluck('id')->all()
        );
        $allServices = Cache::remember('dash:all_services', 300, fn () =>
            Service::whereIn('category_id', array_merge($telegramCatIds, $maxCatIds))
                ->select('id', 'name', 'rate_per_1000', 'priority', 'category_id')
                ->orderBy('priority')
                ->get()
        );

        return [$telegramCatIds, $maxCatIds, $allServices];
    }

    private function buildChartArrays($chartLabels, $chartRows, array $slowBlock): array
    {
        return [
            'chartCompletionsData' => $chartLabels->map(fn ($d) => (int) ($chartRows[$d]->completions ?? 0))->values(),
            'chartIncomeData' => $chartLabels->map(fn ($d) => (float) ($chartRows[$d]->income ?? 0))->values(),
            'chartTgCompletions' => $chartLabels->map(fn ($d) => (int) ($chartRows[$d]->tg_completions ?? 0))->values(),
            'chartMaxCompletions' => $chartLabels->map(fn ($d) => (int) ($chartRows[$d]->max_completions ?? 0))->values(),
            'chartPaymentsData' => $chartLabels->map(fn ($d) => (float) ($slowBlock['dailyPayments'][$d] ?? 0))->values(),
            'chartNewUsersData' => $chartLabels->map(fn ($d) => (int) ($slowBlock['dailyNewUsers'][$d] ?? 0))->values(),
            'chartRefillData' => $chartLabels->map(fn ($d) => (int) ($slowBlock['refillCompletions'][$d] ?? 0))->values(),
            'chartTestMoneyData' => $chartLabels->map(fn ($d) => (float) ($slowBlock['dailyTestMoney'][$d] ?? 0))->values(),
        ];
    }

    private function resolveChartRange(
        bool $hasDateFilter, ?Carbon $dateFromParsed, ?Carbon $dateToParsed,
        string $period, Carbon $todayEnd
    ): array {
        if ($hasDateFilter) {
            $rangeStart = $dateFromParsed ?? now()->subDays(90)->startOfDay();
            $rangeEnd = $dateToParsed ?? $todayEnd;

            if ($rangeStart->diffInDays($rangeEnd) > 90) {
                $period = 'monthly';
                $groupExpr = $this->yearMonthExpr('m.subscribed_at');
                $cursor = $rangeStart->copy()->startOfMonth();
                $end = $rangeEnd->copy()->startOfMonth();
                $labels = collect();
                while ($cursor->lte($end)) {
                    $labels->push($cursor->format('Y-m'));
                    $cursor->addMonth();
                }
            } else {
                $groupExpr = 'DATE(m.subscribed_at)';
                $cursor = $rangeStart->copy();
                $labels = collect();
                while ($cursor->lte($rangeEnd)) {
                    $labels->push($cursor->format('Y-m-d'));
                    $cursor->addDay();
                }
            }
        } elseif ($period === 'monthly') {
            $labels = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
            $rangeStart = now()->subMonths(11)->startOfMonth();
            $rangeEnd = $todayEnd;
            $groupExpr = $this->yearMonthExpr('m.subscribed_at');
        } else {
            $labels = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
            $rangeStart = now()->subDays(6)->startOfDay();
            $rangeEnd = $todayEnd;
            $groupExpr = 'DATE(m.subscribed_at)';
        }

        return [$labels, $rangeStart, $rangeEnd, $groupExpr, $period];
    }

    private function resolveTopMetrics(bool $hasDateFilter, string $period, $chartRows, string $todayStr, string $yesterdayStr): array
    {
        if ($hasDateFilter) {
            return [(float) $chartRows->sum('income'), 0.0, (int) $chartRows->sum('completions'), 0];
        }

        if ($period === 'monthly') {
            $current = now()->format('Y-m');
            $prev = now()->subMonth()->format('Y-m');
            return [
                (float) ($chartRows[$current]->income ?? 0),
                (float) ($chartRows[$prev]->income ?? 0),
                (int) ($chartRows[$current]->completions ?? 0),
                (int) ($chartRows[$prev]->completions ?? 0),
            ];
        }

        return [
            (float) ($chartRows[$todayStr]->income ?? 0),
            (float) ($chartRows[$yesterdayStr]->income ?? 0),
            (int) ($chartRows[$todayStr]->completions ?? 0),
            (int) ($chartRows[$yesterdayStr]->completions ?? 0),
        ];
    }

    private function yearMonthExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
