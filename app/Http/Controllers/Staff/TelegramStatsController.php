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
    private const CACHE_TTL = 60; // seconds

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

        // Cache key based on request parameters
        $cacheKey = 'tg_stats:data:' . md5(json_encode([
            'period' => $period,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'service_id' => $serviceId,
        ]));

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use (
            $period, $hasDateFilter, $dateFromParsed, $dateToParsed,
            $filteredServiceIds, $telegramServiceIds
        ) {
            $todayStr = now()->format('Y-m-d');
            $yesterdayStr = now()->subDay()->format('Y-m-d');
            $todayEnd = now()->endOfDay();

            [$chartLabels, $rangeStart, $rangeEnd, $groupExpr, $period] =
                $this->resolveChartRange($hasDateFilter, $dateFromParsed, $dateToParsed, $period, $todayEnd);

            // Chart data
            $chartRows = DB::table('telegram_order_memberships as m')
                ->join('orders as o', 'o.id', '=', 'm.order_id')
                ->join('services as s', 's.id', '=', 'o.service_id')
                ->whereIn('o.service_id', $filteredServiceIds)
                ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('m.subscribed_at')
                ->where('m.subscribed_at', '>=', $rangeStart)
                ->where('m.subscribed_at', '<=', $rangeEnd)
                ->selectRaw("{$groupExpr} as period, COUNT(*) as completions, COALESCE(SUM(s.rate_per_1000 / 1000), 0) as income")
                ->groupByRaw($groupExpr)
                ->get()
                ->keyBy('period');

            [$incomeToday, $incomeYesterday, $completionsToday, $completionsYesterday] =
                $this->resolveTopMetrics($hasDateFilter, $period, $chartRows, $todayStr, $yesterdayStr);

            // Per-service order stats
            $serviceStats = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->selectRaw("
                    service_id,
                    COUNT(*) as total_orders,
                    COALESCE(SUM(CASE WHEN status = ? AND COALESCE(quantity, 0) > COALESCE(delivered, 0) THEN COALESCE(quantity, 0) - COALESCE(delivered, 0) ELSE 0 END), 0) as total_volume,
                    COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_balance,
                    COALESCE(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END), 0) as completed_orders,
                    COALESCE(SUM(CASE WHEN status = ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END), 0) as completed_balance
                ", [Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED, Order::STATUS_COMPLETED])
                ->groupBy('service_id')
                ->get()
                ->keyBy('service_id');

            // Per-service completions
            $completionsQuery = DB::table('telegram_order_memberships as m')
                ->join('orders as o', 'o.id', '=', 'm.order_id')
                ->whereIn('o.service_id', $telegramServiceIds)
                ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('m.subscribed_at');

            if ($hasDateFilter) {
                if ($dateFromParsed) {
                    $completionsQuery->where('m.subscribed_at', '>=', $dateFromParsed);
                }
                if ($dateToParsed) {
                    $completionsQuery->where('m.subscribed_at', '<=', $dateToParsed);
                }
            } else {
                $completionsQuery->whereRaw('DATE(m.subscribed_at) = ?', [$todayStr]);
            }

            $perServiceRows = $completionsQuery
                ->selectRaw('o.service_id, COUNT(*) as completions_today')
                ->groupBy('o.service_id')
                ->get()
                ->keyBy('service_id');

            // Active accounts: distinct phones that attempted to claim in the last hour (from Redis HLL)
            $currentHour = now()->format('Y-m-d-H');
            $prevHour = now()->subHour()->format('Y-m-d-H');
            $activeAccounts = collect();
            foreach ($telegramServiceIds as $sid) {
                try {
                    $countCurrent = (int) Redis::pfcount("tg:claim_attempts:{$sid}:{$currentHour}");
                    $countPrev = (int) Redis::pfcount("tg:claim_attempts:{$sid}:{$prevHour}");
                    $count = max($countCurrent, $countPrev);
                } catch (\Throwable) {
                    $count = 0;
                }
                if ($count > 0) {
                    $activeAccounts[$sid] = $count;
                }
            }

            return [
                'chartLabels' => $chartLabels,
                'chartIncomeData' => $chartLabels->map(fn ($d) => (float) ($chartRows[$d]->income ?? 0))->values(),
                'chartCompletionsData' => $chartLabels->map(fn ($d) => (int) ($chartRows[$d]->completions ?? 0))->values(),
                'incomeToday' => $incomeToday,
                'incomeYesterday' => $incomeYesterday,
                'completionsToday' => $completionsToday,
                'completionsYesterday' => $completionsYesterday,
                'serviceStats' => $serviceStats,
                'completionsTodayPerService' => $perServiceRows->map(fn ($r) => (int) $r->completions_today),
                'accountsPerService' => $activeAccounts,
                'period' => $period,
            ];
        });

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
            $rangeStart = $dateFromParsed ?? Carbon::parse('2020-01-01');
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
            'serviceStats' => collect(), 'completionsTodayPerService' => collect(), 'accountsPerService' => collect(),
            'period' => $period, 'dateFrom' => $dateFrom, 'dateTo' => $dateTo,
            'hasDateFilter' => (bool) ($dateFrom || $dateTo), 'serviceId' => $serviceId,
            'completionsColumnLabel' => __('Completions today'),
        ]);
    }
}
