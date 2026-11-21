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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TelegramStatsController extends Controller
{
    /**
     * Cache windows as [fresh, stale] seconds for Cache::flexible: within
     * "fresh" the cached value is served as is; until "stale" it is still
     * served instantly while a refresh runs after the response is sent, so no
     * request ever waits for the heavy SQL once the cache is warm.
     */
    private const CACHE_SLOW = [300, 900];        // chart + order aggregates

    private const CACHE_HISTORICAL = [3600, 7200]; // date filter fully in the past

    private const CACHE_LIVE = [45, 120];          // per-service completions, accounts, delay

    private const PER_PAGE = 25;

    /** Membership states that mean the subscription was delivered. */
    private const COMPLETED_MEMBERSHIP_STATES = [
        TelegramOrderMembership::STATE_SUBSCRIBED,
        TelegramOrderMembership::STATE_UNSUBSCRIBED,
    ];

    /** Order statuses the claim pipeline still delivers for. */
    private const ACTIVE_ORDER_STATUSES = [
        Order::STATUS_AWAITING,
        Order::STATUS_IN_PROGRESS,
        Order::STATUS_PROCESSING,
    ];

    /** Orders that were rejected or refunded — they bring no money. */
    private const UNPAID_ORDER_STATUSES = [
        Order::STATUS_CANCELED,
        Order::STATUS_INVALID_LINK,
        Order::STATUS_FAIL,
    ];

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

    /**
     * Return the page shell instantly — no SQL, no cache lookup.
     * Data is loaded lazily by the browser via the /data endpoint.
     */
    public function index(Request $request): View
    {
        [$scope, $period] = $this->scopeAndPeriod($request);

        return view('staff.telegram-stats.index', [
            'scope'      => $scope,
            'period'     => $period,
            'dateFrom'   => $request->input('date_from'),
            'dateTo'     => $request->input('date_to'),
            'serviceId'  => $request->input('service_id'),
            'dataUrl'    => route('staff.telegram-stats.data', $request->query()),
        ]);
    }

    /**
     * Heavy SQL endpoint — called by the browser after page render.
     * Two cached blocks, three SQL queries and one Redis round-trip in total.
     */
    public function data(Request $request): JsonResponse
    {
        [$scope, $period] = $this->scopeAndPeriod($request);

        $dateFrom       = $request->input('date_from');
        $dateTo         = $request->input('date_to');
        $hasDateFilter  = (bool) ($dateFrom || $dateTo);
        $dateFromParsed = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $dateToParsed   = $dateTo   ? Carbon::parse($dateTo)->endOfDay()     : null;
        $page           = max(1, (int) $request->input('page', 1));

        $telegramServices = $this->telegramServices($scope);

        if ($telegramServices->isEmpty()) {
            return $this->emptyJson($period, $scope);
        }

        $serviceId = (int) $request->input('service_id');
        $displayedServices = $serviceId && $telegramServices->contains('id', $serviceId)
            ? $telegramServices->where('id', $serviceId)->values()
            : $telegramServices;
        $serviceIds = $displayedServices->pluck('id')->all();
        $rateMap    = $telegramServices->pluck('rate_per_1000', 'id');

        [$chartLabels, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod] =
            $this->resolveChartRange($hasDateFilter, $dateFromParsed, $dateToParsed, $period, now()->endOfDay());

        // Orders placed in the same window as the top cards: today, this
        // month, or the selected date range.
        [$ordersFrom, $ordersTo] = match (true) {
            $hasDateFilter                => [$rangeStart, $rangeEnd],
            $resolvedPeriod === 'monthly' => [now()->startOfMonth(), now()],
            default                       => [now()->startOfDay(), now()],
        };

        $isHistorical = $hasDateFilter && $dateToParsed && $dateToParsed->diffInHours(now(), true) >= 2;

        $slowKey = 'tg_stats:slow:v4:'.md5(json_encode([$scope, $resolvedPeriod, $dateFrom, $dateTo, $serviceIds]));
        $slow = Cache::flexible($slowKey, $isHistorical ? self::CACHE_HISTORICAL : self::CACHE_SLOW,
            fn () => $this->buildSlowBlock($serviceIds, $rateMap, $hasDateFilter, $resolvedPeriod, $rangeStart, $rangeEnd, $groupExpr, $ordersFrom, $ordersTo));

        // Per-service completions: today, or the selected date range.
        $completionsFrom = $hasDateFilter ? $dateFromParsed : now()->startOfDay();
        $completionsTo   = $hasDateFilter ? $dateToParsed : null;

        $liveKey = 'tg_stats:live:v1:'.md5(json_encode([$dateFrom, $dateTo, $serviceIds]));
        $live = Cache::flexible($liveKey, self::CACHE_LIVE,
            fn () => $this->buildLiveBlock($serviceIds, $completionsFrom, $completionsTo));

        $serviceStats = $slow['serviceStats'];
        $completions  = $live['completions'];
        $incomeFor    = fn (int $sid) => ($completions[$sid] ?? 0) * (float) ($rateMap[$sid] ?? 0) / 1000;

        $paginatedServices = new LengthAwarePaginator(
            $displayedServices->forPage($page, self::PER_PAGE),
            $displayedServices->count(),
            self::PER_PAGE,
            $page,
        );

        $servicesData = $paginatedServices->getCollection()->map(function ($service) use ($serviceStats, $live, $completions, $incomeFor) {
            $stats = $serviceStats[$service->id] ?? null;

            return [
                'id'                 => $service->id,
                'name'               => $service->name,
                'rate_per_1000'      => (float) $service->rate_per_1000,
                'accounts'           => (int) ($live['accounts'][$service->id] ?? 0),
                'delay_sec'          => $live['delay'][$service->id] ?? null,
                'completions_today'  => (int) ($completions[$service->id] ?? 0),
                'income_today'       => round($incomeFor($service->id), 4),
                'in_progress_orders' => (int) ($stats->in_progress_orders ?? 0),
                'total_volume'       => (int) ($stats->total_volume ?? 0),
                'completed_orders'   => (int) ($stats->completed_orders ?? 0),
                'completed_balance'  => (float) ($stats->completed_balance ?? 0),
            ];
        });

        // Totals across ALL displayed services (not just the current page).
        $totals = [
            'completions_today'  => $completions->sum(),
            'accounts'           => $live['accountsTotal'],
            'in_progress_orders' => $serviceStats->sum('in_progress_orders'),
            'total_volume'       => $serviceStats->sum('total_volume'),
            'income_today'       => $displayedServices->sum(fn ($s) => $incomeFor($s->id)),
            'completed_orders'   => $serviceStats->sum('completed_orders'),
            'completed_balance'  => $serviceStats->sum('completed_balance'),
        ];

        $chartRows = $slow['chartRows'];

        return response()->json([
            // Top metrics
            'income_today'          => (float) $slow['incomeToday'],
            'income_yesterday'      => (float) $slow['incomeYesterday'],
            'completions_today'     => (int)   $slow['completionsToday'],
            'completions_yesterday' => (int)   $slow['completionsYesterday'],
            'orders_count'          => (int)   $slow['ordersCount'],
            'orders_total'          => (float) $slow['ordersTotal'],

            // Chart
            'chart_labels'      => $chartLabels->values(),
            'chart_income'      => $chartLabels->map(fn ($d) => (float) ($chartRows[$d]->income      ?? 0))->values(),
            'chart_completions' => $chartLabels->map(fn ($d) => (int)   ($chartRows[$d]->completions ?? 0))->values(),
            'period'            => $resolvedPeriod,

            // Services table
            'services'          => $servicesData->values(),
            'telegramServices'  => $telegramServices->map(fn ($s) => ['id' => $s->id, 'name' => $s->name]),
            'pagination'        => [
                'current_page' => $paginatedServices->currentPage(),
                'last_page'    => $paginatedServices->lastPage(),
                'total'        => $paginatedServices->total(),
                'per_page'     => self::PER_PAGE,
            ],
            'totals'            => $totals,

            // Meta
            'has_date_filter'          => $hasDateFilter,
            'completions_column_label' => $hasDateFilter ? __('Completions') : __('Completions today'),
        ]);
    }

    // ── Blocks ────────────────────────────────────────────────────────────────

    /**
     * Chart, top cards and order aggregates. Two queries: one over
     * memberships for the chart range, one over orders for everything else.
     */
    private function buildSlowBlock(
        array $serviceIds, Collection $rateMap, bool $hasDateFilter, string $period,
        Carbon $rangeStart, Carbon $rangeEnd, string $groupExpr, Carbon $ordersFrom, Carbon $ordersTo
    ): array {
        // A completion is a membership that reached "subscribed" — including
        // ones that later expired to "unsubscribed", otherwise past days
        // would shrink as subscriptions run out.
        $rawRows = DB::table('telegram_order_memberships as m')
            ->whereIn('m.service_id', $serviceIds)
            ->whereIn('m.state', self::COMPLETED_MEMBERSHIP_STATES)
            ->whereBetween('m.subscribed_at', [$rangeStart, $rangeEnd])
            ->selectRaw("{$groupExpr} as period, m.service_id, COUNT(*) as completions")
            ->groupByRaw("{$groupExpr}, m.service_id")
            ->get();

        $chartRows = $rawRows->groupBy('period')->map(fn ($rows) => (object) [
            'completions' => $rows->sum('completions'),
            'income'      => $rows->sum(fn ($r) => $r->completions * (float) ($rateMap[$r->service_id] ?? 0) / 1000),
        ]);

        [$incomeToday, $incomeYesterday, $completionsToday, $completionsYesterday] =
            $this->resolveTopMetrics($hasDateFilter, $period, $chartRows, now()->format('Y-m-d'), now()->subDay()->format('Y-m-d'));

        // One pass over orders: per-service active/completed figures plus the
        // money of orders placed in the window. A row qualifies if it counts
        // for either; each SUM only picks the rows its CASE matches.
        $statusIn = fn (array $statuses) => "status IN ('".implode("','", $statuses)."')";
        $active   = $statusIn(self::ACTIVE_ORDER_STATUSES);
        $unpaid   = $statusIn(self::UNPAID_ORDER_STATUSES);

        $serviceStats = DB::table('orders')
            ->whereIn('service_id', $serviceIds)
            ->where(fn ($q) => $q
                ->whereIn('status', [...self::ACTIVE_ORDER_STATUSES, Order::STATUS_COMPLETED])
                ->orWhereBetween('created_at', [$ordersFrom, $ordersTo]))
            ->selectRaw("
                service_id,
                SUM(CASE WHEN {$active} AND COALESCE(remains, 0) > 0 THEN remains ELSE 0 END) as total_volume,
                SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as in_progress_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END) as completed_balance,
                SUM(CASE WHEN NOT {$unpaid} AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as window_orders,
                SUM(CASE WHEN NOT {$unpaid} AND created_at BETWEEN ? AND ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END) as window_total
            ", [Order::STATUS_COMPLETED, Order::STATUS_COMPLETED, $ordersFrom, $ordersTo, $ordersFrom, $ordersTo])
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        return [
            'chartRows'            => $chartRows,
            'incomeToday'          => $incomeToday,
            'incomeYesterday'      => $incomeYesterday,
            'completionsToday'     => $completionsToday,
            'completionsYesterday' => $completionsYesterday,
            'serviceStats'         => $serviceStats,
            'ordersCount'          => (int) $serviceStats->sum('window_orders'),
            'ordersTotal'          => (float) $serviceStats->sum('window_total'),
        ];
    }

    /**
     * Per-service completions for the window, delay over the last hour and
     * active accounts. One SQL query and one Redis pipeline.
     */
    private function buildLiveBlock(array $serviceIds, ?Carbon $from, ?Carbon $to): array
    {
        $hourAgo = now()->subHour();

        // Completions in the window and in the last hour come from the same
        // rows, so a single scan returns both counters.
        $window   = [];
        $bindings = [];
        if ($from) { $window[] = 'subscribed_at >= ?'; $bindings[] = $from; }
        if ($to)   { $window[] = 'subscribed_at <= ?'; $bindings[] = $to; }
        $windowSql = $window ? implode(' AND ', $window) : '1 = 1';

        $rows = DB::table('telegram_order_memberships')
            ->whereIn('service_id', $serviceIds)
            ->whereIn('state', self::COMPLETED_MEMBERSHIP_STATES)
            ->whereNotNull('subscribed_at')
            ->whereRaw("(({$windowSql}) OR subscribed_at >= ?)", [...$bindings, $hourAgo])
            ->selectRaw("
                service_id,
                SUM(CASE WHEN {$windowSql} THEN 1 ELSE 0 END) as in_window,
                SUM(CASE WHEN subscribed_at >= ? THEN 1 ELSE 0 END) as last_hour
            ", [...$bindings, $hourAgo])
            ->groupBy('service_id')
            ->get();

        $completions = collect();
        $delay       = collect();
        foreach ($rows as $row) {
            if ((int) $row->in_window > 0) {
                $completions[$row->service_id] = (int) $row->in_window;
            }
            // Average seconds between completions over the last hour.
            if ((int) $row->last_hour > 1) {
                $delay[$row->service_id] = round(3600 / (int) $row->last_hour);
            }
        }

        // PFCOUNT over several keys returns the size of their union, so every
        // account is counted once: per service across both hours ("last 2
        // hours"), and once overall across all services.
        $currentHour = now()->format('Y-m-d-H');
        $prevHour    = now()->subHour()->format('Y-m-d-H');
        $keysFor     = fn ($sid) => ["tg:claim_attempts:{$sid}:{$currentHour}", "tg:claim_attempts:{$sid}:{$prevHour}"];

        $accounts      = collect();
        $accountsTotal = 0;
        try {
            $results = Redis::pipeline(function ($pipe) use ($serviceIds, $keysFor) {
                foreach ($serviceIds as $sid) {
                    $pipe->pfcount($keysFor($sid));
                }
                $pipe->pfcount(array_merge(...array_map($keysFor, $serviceIds)));
            });
            foreach ($serviceIds as $i => $sid) {
                if (($count = (int) ($results[$i] ?? 0)) > 0) {
                    $accounts[$sid] = $count;
                }
            }
            $accountsTotal = (int) ($results[count($serviceIds)] ?? 0);
        } catch (\Throwable) {
        }

        return compact('completions', 'delay', 'accounts', 'accountsTotal');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string} */
    private function scopeAndPeriod(Request $request): array
    {
        $scope = in_array($request->input('scope'), [TelegramPremiumTemplateScope::SCOPE_DEFAULT, TelegramPremiumTemplateScope::SCOPE_PREMIUM], true)
            ? $request->input('scope')
            : TelegramPremiumTemplateScope::SCOPE_DEFAULT;

        $period = in_array($request->input('period'), ['days', 'monthly'], true)
            ? $request->input('period')
            : 'days';

        return [$scope, $period];
    }

    private function telegramServices(string $scope): Collection
    {
        return Cache::remember('tg_stats:services:'.$scope, 600, function () use ($scope) {
            $query = Service::whereIn('category_id', Category::where('link_driver', 'telegram')->select('id'))
                ->select('id', 'name', 'rate_per_1000', 'priority', 'category_id', 'template_key')
                ->orderBy('priority');

            TelegramPremiumTemplateScope::applyServiceTemplateScope($query, $scope);

            return $query->get();
        });
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
                while ($cursor->lte($end)) { $labels->push($cursor->format('Y-m')); $cursor->addMonth(); }
            } else {
                $groupExpr = 'DATE(m.subscribed_at)';
                $cursor    = $rangeStart->copy();
                $labels    = collect();
                while ($cursor->lte($rangeEnd)) { $labels->push($cursor->format('Y-m-d')); $cursor->addDay(); }
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

    private function resolveTopMetrics(bool $hasDateFilter, string $period, $chartRows, string $todayStr, string $yesterdayStr): array
    {
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

    private function emptyJson(string $period, string $scope): JsonResponse
    {
        return response()->json([
            'income_today' => 0, 'income_yesterday' => 0,
            'completions_today' => 0, 'completions_yesterday' => 0,
            'orders_count' => 0, 'orders_total' => 0,
            'chart_labels' => [], 'chart_income' => [], 'chart_completions' => [],
            'period' => $period, 'has_date_filter' => false,
            'services' => [], 'telegramServices' => [], 'totals' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => self::PER_PAGE],
            'completions_column_label' => __('Completions today'),
        ]);
    }
}
