<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramOrderMembership;
use App\Support\TelegramPremiumTemplateScope;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TelegramStatsController extends Controller
{
    /**
     * Chart + order aggregates (heavy SQL, slow-changing).
     * Historical ranges (dateTo > 2 h in the past) get CACHE_TTL_HISTORICAL.
     */
    private const CACHE_TTL_SLOW       = 300;
    private const CACHE_TTL_HISTORICAL = 3600;

    /**
     * Per-service task completions (date-sensitive).
     */
    private const CACHE_TTL_COMPLETIONS = 45;

    /**
     * Active accounts (Redis HLL) + task delay.
     * Not date-sensitive → shared across all date-filter variations for same services.
     */
    private const CACHE_TTL_REALTIME = 60;

    /** Cached DB driver name — avoid repeated reflection per request. */
    private ?string $dbDriver = null;

    private function dbDriver(): string
    {
        return $this->dbDriver ??= DB::connection()->getDriverName();
    }

    private function yearMonthExpr(string $column): string
    {
        return match ($this->dbDriver()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql'  => "TO_CHAR({$column}, 'YYYY-MM')",
            default  => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    public function index(Request $request): View
    {
        $scope = in_array($request->input('scope'), [TelegramPremiumTemplateScope::SCOPE_DEFAULT, TelegramPremiumTemplateScope::SCOPE_PREMIUM], true)
            ? $request->input('scope')
            : TelegramPremiumTemplateScope::SCOPE_DEFAULT;

        $period = in_array($request->input('period'), ['days', 'monthly'], true)
            ? $request->input('period')
            : 'days';

        $dateFrom       = $request->input('date_from');
        $dateTo         = $request->input('date_to');
        $hasDateFilter  = (bool) ($dateFrom || $dateTo);
        $dateFromParsed = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $dateToParsed   = $dateTo   ? Carbon::parse($dateTo)->endOfDay()     : null;
        $serviceId      = $request->input('service_id');

        // ── Services (cached 10 min) ─────────────────────────────────────────
        $telegramServices = Cache::remember('tg_stats:services:' . $scope, 600, function () use ($scope) {
            $catIds = Category::where('link_driver', 'telegram')->pluck('id');

            $query = Service::whereIn('category_id', $catIds)
                ->select('id', 'name', 'rate_per_1000', 'priority', 'category_id', 'template_key')
                ->orderBy('priority');

            TelegramPremiumTemplateScope::applyServiceTemplateScope($query, $scope);

            return $query->get();
        });

        $telegramServiceIds = $telegramServices->pluck('id')->all();

        if (empty($telegramServiceIds)) {
            return $this->emptyView($period, $dateFrom, $dateTo, $serviceId, $scope);
        }

        $filteredServiceIds = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? [(int) $serviceId]
            : $telegramServiceIds;

        $todayEnd = now()->endOfDay();
        [$chartLabels, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod] =
            $this->resolveChartRange($hasDateFilter, $dateFromParsed, $dateToParsed, $period, $todayEnd);

        $rateMap = $telegramServices->pluck('rate_per_1000', 'id');

        // ── Slow block (chart + order aggregates) ────────────────────────────
        // Historical ranges (dateTo > 2 h in the past) are immutable → cache 1 h.
        $isHistorical = $hasDateFilter
            && $dateToParsed
            && $dateToParsed->diffInHours(now(), true) >= 2;

        $slowKey = 'tg_stats:slow:' . md5(json_encode([
            'scope'      => $scope,
            'period'     => $resolvedPeriod,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'svc'        => $filteredServiceIds,  // resolved IDs, not raw input
        ]));

        $slowBlock = Cache::remember($slowKey, $isHistorical ? self::CACHE_TTL_HISTORICAL : self::CACHE_TTL_SLOW,
            function () use ($hasDateFilter, $rateMap, $filteredServiceIds, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod) {
                $todayStr     = now()->format('Y-m-d');
                $yesterdayStr = now()->subDay()->format('Y-m-d');

                // Chart rows — uses idx_tg_memberships_svc_state_sub
                $rawRows = DB::table('telegram_order_memberships as m')
                    ->whereIn('m.service_id', $filteredServiceIds)
                    ->where('m.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                    ->whereNotNull('m.subscribed_at')
                    ->whereBetween('m.subscribed_at', [$rangeStart, $rangeEnd])
                    ->selectRaw("{$groupExpr} as period, m.service_id, COUNT(*) as completions")
                    ->groupByRaw("{$groupExpr}, m.service_id")
                    ->get();

                $chartRows = $rawRows->groupBy('period')->map(fn ($rows) => (object) [
                    'completions' => $rows->sum('completions'),
                    'income'      => $rows->sum(fn ($r) => $r->completions * (float) ($rateMap[$r->service_id] ?? 0) / 1000),
                ]);

                [$incomeToday, $incomeYesterday, $completionsToday, $completionsYesterday] =
                    $this->resolveTopMetrics($hasDateFilter, $resolvedPeriod, $chartRows, $todayStr, $yesterdayStr);

                // Order aggregates — single scan, uses orders(service_id, status) index.
                // SUM(CASE WHEN ... ELSE 0) never returns NULL, so COALESCE is not needed.
                $serviceStats = DB::table('orders')
                    ->whereIn('service_id', $filteredServiceIds)
                    ->whereIn('status', [Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED])
                    ->selectRaw('
                        service_id,
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN status = ? AND COALESCE(quantity,0) > COALESCE(delivered,0)
                                 THEN COALESCE(quantity,0) - COALESCE(delivered,0) ELSE 0 END) as total_volume,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_orders,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END) as completed_balance
                    ', [Order::STATUS_IN_PROGRESS, Order::STATUS_IN_PROGRESS, Order::STATUS_COMPLETED, Order::STATUS_COMPLETED])
                    ->groupBy('service_id')
                    ->get()
                    ->keyBy('service_id');

                return compact('chartRows', 'incomeToday', 'incomeYesterday', 'completionsToday', 'completionsYesterday', 'serviceStats');
            }
        );

        $chartRows = $slowBlock['chartRows'];
        unset($slowBlock['chartRows']);

        // ── Completions per service (date-sensitive, TTL 45 s) ───────────────
        $completionsKey = 'tg_stats:completions:' . md5(json_encode([
            'svc'       => $filteredServiceIds,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]));

        $completionsTodayPerService = Cache::remember($completionsKey, self::CACHE_TTL_COMPLETIONS,
            function () use ($hasDateFilter, $dateFromParsed, $dateToParsed, $filteredServiceIds) {
                $cutoff = $hasDateFilter ? $dateFromParsed : now()->startOfDay();

                $q = DB::table('telegram_tasks as t')
                    ->whereIn('t.service_id', $filteredServiceIds)
                    ->whereIn('t.status', ['done', 'unsubscribed']);

                if ($cutoff) {
                    $q->where('t.updated_at', '>=', $cutoff);
                }
                if ($hasDateFilter && $dateToParsed) {
                    $q->where('t.updated_at', '<=', $dateToParsed);
                }

                // Uses idx_tg_tasks_svc_status_updated
                return $q->selectRaw('t.service_id, COUNT(*) as cnt')
                    ->groupBy('t.service_id')
                    ->pluck('cnt', 'service_id')
                    ->map(fn ($v) => (int) $v);
            }
        );

        // ── Realtime: active accounts + task delay ───────────────────────────
        // NOT date-sensitive → key only contains service IDs.
        // All date-filter variations for the same services share this cache entry.
        $realtimeKey = 'tg_stats:realtime:' . md5(json_encode($filteredServiceIds));

        [$accountsPerService, $delayPerService] = Cache::remember($realtimeKey, self::CACHE_TTL_REALTIME,
            function () use ($filteredServiceIds) {
                // Active accounts via Redis HyperLogLog (2-hour window)
                $currentHour = now()->format('Y-m-d-H');
                $prevHour    = now()->subHour()->format('Y-m-d-H');
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

                // Task delay — uses idx_tg_tasks_svc_created
                $taskCounts = DB::table('telegram_tasks')
                    ->whereIn('service_id', $filteredServiceIds)
                    ->where('created_at', '>=', now()->subHour())
                    ->selectRaw('service_id, COUNT(*) as task_count')
                    ->groupBy('service_id')
                    ->pluck('task_count', 'service_id');

                $delayPerService = collect();
                foreach ($taskCounts as $sid => $count) {
                    if ($count > 1) {
                        $delayPerService[$sid] = round(3600 / $count);
                    }
                }

                return [$activeAccounts, $delayPerService];
            }
        );

        // ── Assemble view data ───────────────────────────────────────────────
        $data = array_merge($slowBlock, [
            'chartLabels'                => $chartLabels,
            'chartIncomeData'            => $chartLabels->map(fn ($d) => (float) ($chartRows[$d]->income      ?? 0))->values(),
            'chartCompletionsData'       => $chartLabels->map(fn ($d) => (int)   ($chartRows[$d]->completions ?? 0))->values(),
            'period'                     => $resolvedPeriod,
            'completionsTodayPerService' => $completionsTodayPerService,
            'accountsPerService'         => $accountsPerService,
            'delayPerService'            => $delayPerService,
        ]);

        $allDisplayedServices = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? $telegramServices->where('id', (int) $serviceId)->values()
            : $telegramServices;

        // Paginate in-memory — the collection is already cached, no extra SQL.
        $perPage     = 25;
        $currentPage = max(1, (int) $request->input('page', 1));
        $paginatedServices = new LengthAwarePaginator(
            $allDisplayedServices->forPage($currentPage, $perPage),
            $allDisplayedServices->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('staff.telegram-stats.index', array_merge($data, [
            'telegramServices'       => $telegramServices,
            'displayedServices'      => $paginatedServices,   // paginator (iterable, has ->links())
            'allDisplayedServices'   => $allDisplayedServices, // full collection for totals row
            'dateFrom'               => $dateFrom,
            'dateTo'                 => $dateTo,
            'hasDateFilter'          => $hasDateFilter,
            'serviceId'              => $serviceId,
            'completionsColumnLabel' => $hasDateFilter ? __('Completions') : __('Completions today'),
            'scope'                  => $scope,
        ]));
    }

    private function resolveChartRange(
        bool $hasDateFilter, ?Carbon $dateFromParsed, ?Carbon $dateToParsed,
        string $period, Carbon $todayEnd
    ): array {
        if ($hasDateFilter) {
            $rangeStart = $dateFromParsed ?? now()->subDays(90)->startOfDay();
            $rangeEnd   = $dateToParsed  ?? $todayEnd;

            if ($rangeStart->diffInDays($rangeEnd) > 90) {
                $period    = 'monthly';
                $groupExpr = $this->yearMonthExpr('m.subscribed_at');
                $cursor    = $rangeStart->copy()->startOfMonth();
                $end       = $rangeEnd->copy()->startOfMonth();
                $labels    = collect();
                while ($cursor->lte($end)) {
                    $labels->push($cursor->format('Y-m'));
                    $cursor->addMonth();
                }
            } else {
                $groupExpr = 'DATE(m.subscribed_at)';
                $cursor    = $rangeStart->copy();
                $labels    = collect();
                while ($cursor->lte($rangeEnd)) {
                    $labels->push($cursor->format('Y-m-d'));
                    $cursor->addDay();
                }
            }
        } elseif ($period === 'monthly') {
            $labels     = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
            $rangeStart = now()->subMonths(11)->startOfMonth();
            $rangeEnd   = $todayEnd;
            $groupExpr  = $this->yearMonthExpr('m.subscribed_at');
        } else {
            $labels     = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
            $rangeStart = now()->subDays(6)->startOfDay();
            $rangeEnd   = $todayEnd;
            $groupExpr  = 'DATE(m.subscribed_at)';
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
            $prev    = now()->subMonth()->format('Y-m');

            return [
                (float) ($chartRows[$current]->income      ?? 0),
                (float) ($chartRows[$prev]->income         ?? 0),
                (int)   ($chartRows[$current]->completions ?? 0),
                (int)   ($chartRows[$prev]->completions    ?? 0),
            ];
        }

        return [
            (float) ($chartRows[$todayStr]->income         ?? 0),
            (float) ($chartRows[$yesterdayStr]->income     ?? 0),
            (int)   ($chartRows[$todayStr]->completions    ?? 0),
            (int)   ($chartRows[$yesterdayStr]->completions ?? 0),
        ];
    }

    private function emptyView(
        string $period,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $serviceId = null,
        string $scope = TelegramPremiumTemplateScope::SCOPE_DEFAULT
    ): View {
        return view('staff.telegram-stats.index', [
            'incomeToday'                => 0,
            'incomeYesterday'            => 0,
            'completionsToday'           => 0,
            'completionsYesterday'       => 0,
            'chartLabels'                => collect(),
            'chartIncomeData'            => collect(),
            'chartCompletionsData'       => collect(),
            'telegramServices'           => collect(),
            'displayedServices'          => new LengthAwarePaginator(collect(), 0, 25),
            'allDisplayedServices'       => collect(),
            'serviceStats'               => collect(),
            'delayPerService'            => collect(),
            'completionsTodayPerService' => collect(),
            'accountsPerService'         => collect(),
            'period'                     => $period,
            'dateFrom'                   => $dateFrom,
            'dateTo'                     => $dateTo,
            'hasDateFilter'              => (bool) ($dateFrom || $dateTo),
            'serviceId'                  => $serviceId,
            'completionsColumnLabel'     => __('Completions today'),
            'scope'                      => $scope,
        ]);
    }
}
