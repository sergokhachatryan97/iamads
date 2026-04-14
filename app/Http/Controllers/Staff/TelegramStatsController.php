<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramOrderMembership;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TelegramStatsController extends Controller
{
    /** Chart + order aggregates (heavier SQL). */
    private const CACHE_TTL_SLOW = 300;

    /** Task counts, Redis active accounts, delay estimates. */
    private const CACHE_TTL_LIVE = 45;

    private function yearMonthExpr(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

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

        $telegramServiceIds = Cache::remember('tg_stats:service_ids', 300, function () {
            $catIds = Category::where('link_driver', 'telegram')->pluck('id');

            return Service::whereIn('category_id', $catIds)->pluck('id')->all();
        });

        $telegramServices = Cache::remember('tg_stats:services', 300, function () use ($telegramServiceIds) {
            return Service::whereIn('id', $telegramServiceIds)->orderBy('priority')->get();
        });

        if (empty($telegramServiceIds)) {
            return $this->emptyView($period, $dateFrom, $dateTo, $serviceId);
        }

        $filteredServiceIds = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? [(int) $serviceId]
            : $telegramServiceIds;

        // Build a rate lookup from cached services (avoids joining services table in queries)
        $rateMap = $telegramServices->pluck('rate_per_1000', 'id');

        $todayEnd = now()->endOfDay();
        [$chartLabels, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod] =
            $this->resolveChartRange($hasDateFilter, $dateFromParsed, $dateToParsed, $period, $todayEnd);

        $keyPayload = [
            'period' => $resolvedPeriod,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'service_id' => $serviceId,
            'svc' => $filteredServiceIds,
        ];
        $slowKey = 'tg_stats:slow:'.md5(json_encode($keyPayload));
        $liveKey = 'tg_stats:live:'.md5(json_encode($keyPayload));

        $slowBlock = Cache::remember($slowKey, self::CACHE_TTL_SLOW, function () use (
            $hasDateFilter,
            $filteredServiceIds, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod, $rateMap
        ) {
            $todayStr = now()->startOfDay()->format('Y-m-d');
            $yesterdayStr = now()->subDay()->format('Y-m-d');

            // Chart: 2-table join instead of 3 — compute income in PHP from cached rates
            $chartRows = DB::table('telegram_order_memberships as m')
                ->join('orders as o', 'o.id', '=', 'm.order_id')
                ->whereIn('o.service_id', $filteredServiceIds)
                ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('m.subscribed_at')
                ->where('m.subscribed_at', '>=', $rangeStart)
                ->where('m.subscribed_at', '<=', $rangeEnd)
                ->selectRaw("{$groupExpr} as period, o.service_id, COUNT(*) as completions")
                ->groupByRaw("{$groupExpr}, o.service_id")
                ->get();

            // Aggregate per-period: sum completions and compute income from rate map
            $chartAggregated = $chartRows->groupBy('period')->map(function ($rows) use ($rateMap) {
                $completions = $rows->sum('completions');
                $income = $rows->sum(fn ($r) => $r->completions * (float) ($rateMap[$r->service_id] ?? 0) / 1000);

                return (object) ['completions' => $completions, 'income' => $income];
            });

            [$incomeToday, $incomeYesterday, $completionsToday, $completionsYesterday] =
                $this->resolveTopMetrics($hasDateFilter, $resolvedPeriod, $chartAggregated, $todayStr, $yesterdayStr);

            // Split into two targeted queries instead of one heavy scan with 4 CASE expressions
            $inProgressStats = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->where('status', Order::STATUS_IN_PROGRESS)
                ->selectRaw('service_id, COUNT(*) as in_progress_orders, COALESCE(SUM(GREATEST(COALESCE(quantity,0) - COALESCE(delivered,0), 0)), 0) as total_volume')
                ->groupBy('service_id')
                ->get()
                ->keyBy('service_id');

            $completedStats = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->where('status', Order::STATUS_COMPLETED)
                ->selectRaw('service_id, COUNT(*) as completed_orders, COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as completed_balance')
                ->groupBy('service_id')
                ->get()
                ->keyBy('service_id');

            // Merge into a unified collection
            $serviceStats = collect($filteredServiceIds)->mapWithKeys(function ($sid) use ($inProgressStats, $completedStats) {
                $ip = $inProgressStats[$sid] ?? null;
                $co = $completedStats[$sid] ?? null;

                return [$sid => (object) [
                    'in_progress_orders' => (int) ($ip->in_progress_orders ?? 0),
                    'total_volume' => (int) ($ip->total_volume ?? 0),
                    'completed_orders' => (int) ($co->completed_orders ?? 0),
                    'completed_balance' => (float) ($co->completed_balance ?? 0),
                ]];
            });

            return [
                'chartRows' => $chartAggregated,
                'incomeToday' => $incomeToday,
                'incomeYesterday' => $incomeYesterday,
                'completionsToday' => $completionsToday,
                'completionsYesterday' => $completionsYesterday,
                'serviceStats' => $serviceStats,
            ];
        });

        $chartRows = $slowBlock['chartRows'];
        unset($slowBlock['chartRows']);

        $liveBlock = Cache::remember($liveKey, self::CACHE_TTL_LIVE, function () use (
            $hasDateFilter, $dateFromParsed, $dateToParsed, $filteredServiceIds
        ) {
            $today = now()->startOfDay();

            // Use subquery for order_ids to avoid joining orders in the main scan
            $orderIds = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->whereIn('status', [Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED])
                ->select('id', 'service_id');

            $completionsQuery = DB::table('telegram_tasks as t')
                ->joinSub($orderIds, 'o', 'o.id', '=', 't.order_id')
                ->whereIn('t.status', ['done', 'unsubscribed']);

            if ($hasDateFilter) {
                if ($dateFromParsed) {
                    $completionsQuery->where('t.updated_at', '>=', $dateFromParsed);
                }
                if ($dateToParsed) {
                    $completionsQuery->where('t.updated_at', '<=', $dateToParsed);
                }
            } else {
                $completionsQuery->where('t.updated_at', '>=', $today);
            }

            $perServiceRows = $completionsQuery
                ->selectRaw('o.service_id, COUNT(*) as completions_today')
                ->groupBy('o.service_id')
                ->get()
                ->keyBy('service_id');

            $currentHour = now()->format('Y-m-d-H');
            $prevHour = now()->subHour()->format('Y-m-d-H');
            $activeAccounts = collect();
            try {
                $results = Redis::pipeline(function ($pipe) use ($filteredServiceIds, $currentHour, $prevHour) {
                    foreach ($filteredServiceIds as $sid) {
                        $pipe->pfcount("tg:claim_attempts:{$sid}:{$currentHour}");
                        $pipe->pfcount("tg:claim_attempts:{$sid}:{$prevHour}");
                    }
                });
                foreach ($filteredServiceIds as $i => $sid) {
                    $count = max((int) ($results[$i * 2] ?? 0), (int) ($results[$i * 2 + 1] ?? 0));
                    if ($count > 0) {
                        $activeAccounts[$sid] = $count;
                    }
                }
            } catch (\Throwable) {
            }

            // Use order_id subquery to filter by service without joining full orders table
            $taskOrderIds = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->select('id', 'service_id');

            $tasksLastHour = DB::table('telegram_tasks as t')
                ->joinSub($taskOrderIds, 'o', 'o.id', '=', 't.order_id')
                ->where('t.created_at', '>=', now()->subHour())
                ->selectRaw('o.service_id, COUNT(*) as task_count')
                ->groupBy('o.service_id')
                ->pluck('task_count', 'service_id');

            $delayPerService = collect();
            foreach ($tasksLastHour as $sid => $count) {
                if ($count > 1) {
                    $delayPerService[$sid] = round(3600 / $count);
                }
            }

            return [
                'delayPerService' => $delayPerService,
                'completionsTodayPerService' => $perServiceRows->map(fn ($r) => (int) $r->completions_today),
                'accountsPerService' => $activeAccounts,
            ];
        });

        $data = array_merge($slowBlock, $liveBlock, [
            'chartLabels' => $chartLabels,
            'chartIncomeData' => $chartLabels->map(fn ($d) => (float) ($chartRows[$d]->income ?? 0))->values(),
            'chartCompletionsData' => $chartLabels->map(fn ($d) => (int) ($chartRows[$d]->completions ?? 0))->values(),
            'period' => $resolvedPeriod,
        ]);

        $completionsColumnLabel = $hasDateFilter ? __('Completions') : __('Completions today');

        $displayedServices = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? $telegramServices->where('id', (int) $serviceId)->values()
            : $telegramServices;

        return view('staff.telegram-stats.index', array_merge($data, [
            'telegramServices' => $telegramServices,
            'displayedServices' => $displayedServices,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'hasDateFilter' => $hasDateFilter,
            'serviceId' => $serviceId,
            'completionsColumnLabel' => $completionsColumnLabel,
        ]));
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

    private function resolveTopMetrics(
        bool $hasDateFilter, string $period, $chartRows,
        string $todayStr, string $yesterdayStr
    ): array {
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

    private function emptyView(string $period, ?string $dateFrom = null, ?string $dateTo = null, ?string $serviceId = null): View
    {
        return view('staff.telegram-stats.index', [
            'incomeToday' => 0, 'incomeYesterday' => 0,
            'completionsToday' => 0, 'completionsYesterday' => 0,
            'chartLabels' => collect(), 'chartIncomeData' => collect(), 'chartCompletionsData' => collect(),
            'telegramServices' => collect(), 'displayedServices' => collect(),
            'serviceStats' => collect(), 'delayPerService' => collect(), 'completionsTodayPerService' => collect(), 'accountsPerService' => collect(),
            'period' => $period, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo,
            'hasDateFilter' => (bool) ($dateFrom || $dateTo), 'serviceId' => $serviceId,
            'completionsColumnLabel' => __('Completions today'),
        ]);
    }
}
