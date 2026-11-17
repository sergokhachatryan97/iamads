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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class TelegramStatsController extends Controller
{
    /** Chart + order aggregates — heavy SQL, changes slowly. */
    private const CACHE_TTL_SLOW = 300;

    /** Historical filter result — data is immutable, cache aggressively. */
    private const CACHE_TTL_HISTORICAL = 3600;

    /** Live task counts, delay estimates. */
    private const CACHE_TTL_COMPLETIONS = 45;

    /** Active accounts + delay — not date-sensitive. */
    private const CACHE_TTL_REALTIME = 60;

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
        $scope = in_array($request->input('scope'), [TelegramPremiumTemplateScope::SCOPE_DEFAULT, TelegramPremiumTemplateScope::SCOPE_PREMIUM], true)
            ? $request->input('scope')
            : TelegramPremiumTemplateScope::SCOPE_DEFAULT;

        $period = in_array($request->input('period'), ['days', 'monthly'], true)
            ? $request->input('period')
            : 'days';

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
     * Results are cached so subsequent calls are instant.
     */
    public function data(Request $request): JsonResponse
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
        $page           = max(1, (int) $request->input('page', 1));

        // ── Services ─────────────────────────────────────────────────────────
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
            return $this->emptyJson($period, $scope);
        }

        $filteredServiceIds = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? [(int) $serviceId]
            : $telegramServiceIds;

        $todayEnd = now()->endOfDay();
        [$chartLabels, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod] =
            $this->resolveChartRange($hasDateFilter, $dateFromParsed, $dateToParsed, $period, $todayEnd);

        $rateMap = $telegramServices->pluck('rate_per_1000', 'id');

        $isHistorical = $hasDateFilter
            && $dateToParsed
            && $dateToParsed->diffInHours(now(), true) >= 2;

        // ── Slow block ────────────────────────────────────────────────────────
        $slowKey = 'tg_stats:slow:v2:' . md5(json_encode([
            'scope'     => $scope,
            'period'    => $resolvedPeriod,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'svc'       => $filteredServiceIds,
        ]));

        $slowBlock = Cache::remember(
            $slowKey,
            $isHistorical ? self::CACHE_TTL_HISTORICAL : self::CACHE_TTL_SLOW,
            function () use ($hasDateFilter, $rateMap, $filteredServiceIds, $rangeStart, $rangeEnd, $groupExpr, $resolvedPeriod) {
                $todayStr     = now()->format('Y-m-d');
                $yesterdayStr = now()->subDay()->format('Y-m-d');

                // A completion is a membership that reached "subscribed" — including
                // ones that later expired to "unsubscribed", otherwise past days
                // would shrink as subscriptions run out.
                $rawRows = DB::table('telegram_order_memberships as m')
                    ->whereIn('m.service_id', $filteredServiceIds)
                    ->whereIn('m.state', self::COMPLETED_MEMBERSHIP_STATES)
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

                // "Active" = every status the claim pipeline still hands out tasks
                // for (see TelegramTaskClaimService), not only in_progress.
                $active = "status IN ('".implode("','", self::ACTIVE_ORDER_STATUSES)."')";

                $serviceStats = DB::table('orders')
                    ->whereIn('service_id', $filteredServiceIds)
                    ->whereIn('status', [...self::ACTIVE_ORDER_STATUSES, Order::STATUS_COMPLETED])
                    ->selectRaw("
                        service_id,
                        COUNT(*) as total_orders,
                        SUM(CASE WHEN {$active} AND COALESCE(remains,0) > 0
                                 THEN COALESCE(remains,0) ELSE 0 END) as total_volume,
                        SUM(CASE WHEN {$active} THEN 1 ELSE 0 END) as in_progress_orders,
                        SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed_orders,
                        SUM(CASE WHEN status = ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END) as completed_balance
                    ", [Order::STATUS_COMPLETED, Order::STATUS_COMPLETED])
                    ->groupBy('service_id')
                    ->get()
                    ->keyBy('service_id');

                return compact('chartRows', 'incomeToday', 'incomeYesterday', 'completionsToday', 'completionsYesterday', 'serviceStats');
            }
        );

        $chartRows   = $slowBlock['chartRows'];
        $serviceStats = $slowBlock['serviceStats'];
        unset($slowBlock['chartRows'], $slowBlock['serviceStats']);

        // ── Completions per service ───────────────────────────────────────────
        $completionsKey = 'tg_stats:completions:v2:' . md5(json_encode([
            'svc'       => $filteredServiceIds,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]));

        $completionsTodayPerService = Cache::remember($completionsKey, self::CACHE_TTL_COMPLETIONS, function () use (
            $hasDateFilter, $dateFromParsed, $dateToParsed, $filteredServiceIds
        ) {
            $cutoff = $hasDateFilter ? $dateFromParsed : now()->startOfDay();

            // Same source as the chart and top cards. telegram_tasks.updated_at
            // is not a completion time: expiring subscriptions flip old "done"
            // tasks to "unsubscribed" and would be counted (and billed) today.
            $q = DB::table('telegram_order_memberships as m')
                ->whereIn('m.service_id', $filteredServiceIds)
                ->whereIn('m.state', self::COMPLETED_MEMBERSHIP_STATES)
                ->whereNotNull('m.subscribed_at');

            if ($cutoff) $q->where('m.subscribed_at', '>=', $cutoff);
            if ($hasDateFilter && $dateToParsed) $q->where('m.subscribed_at', '<=', $dateToParsed);

            return $q->selectRaw('m.service_id, COUNT(*) as cnt')
                ->groupBy('m.service_id')
                ->pluck('cnt', 'service_id')
                ->map(fn ($v) => (int) $v);
        });

        // ── Realtime (accounts + delay) ───────────────────────────────────────
        $realtimeKey = 'tg_stats:realtime:v2:' . md5(json_encode($filteredServiceIds));

        [$accountsPerService, $accountsTotal, $delayPerService] = Cache::remember($realtimeKey, self::CACHE_TTL_REALTIME, function () use ($filteredServiceIds) {
            $currentHour = now()->format('Y-m-d-H');
            $prevHour    = now()->subHour()->format('Y-m-d-H');
            $activeAccounts = collect();
            $accountsTotal  = 0;

            // PFCOUNT over several keys returns the size of their union, so
            // every account is counted once: per service across both hours
            // ("last 2 hours"), and once overall across all services. Summing
            // per-service counts would count an account once per service polled.
            $keysFor = fn ($sid) => ["tg:claim_attempts:{$sid}:{$currentHour}", "tg:claim_attempts:{$sid}:{$prevHour}"];

            try {
                $results = Redis::pipeline(function ($pipe) use ($filteredServiceIds, $keysFor) {
                    foreach ($filteredServiceIds as $sid) {
                        $pipe->pfcount($keysFor($sid));
                    }
                    $pipe->pfcount(array_merge(...array_map($keysFor, $filteredServiceIds)));
                });
                foreach ($filteredServiceIds as $i => $sid) {
                    $count = (int) ($results[$i] ?? 0);
                    if ($count > 0) $activeAccounts[$sid] = $count;
                }
                $accountsTotal = (int) ($results[count($filteredServiceIds)] ?? 0);
            } catch (\Throwable) {}

            // Average seconds between completions over the last hour. Not
            // telegram_tasks.created_at: preassign creates pending tasks ahead
            // of time, which says nothing about delivery speed.
            $completedLastHour = DB::table('telegram_order_memberships')
                ->whereIn('service_id', $filteredServiceIds)
                ->whereIn('state', self::COMPLETED_MEMBERSHIP_STATES)
                ->where('subscribed_at', '>=', now()->subHour())
                ->selectRaw('service_id, COUNT(*) as cnt')
                ->groupBy('service_id')
                ->pluck('cnt', 'service_id');

            $delayPerService = collect();
            foreach ($completedLastHour as $sid => $count) {
                if ($count > 1) $delayPerService[$sid] = round(3600 / $count);
            }

            return [$activeAccounts, $accountsTotal, $delayPerService];
        });

        // ── Build services list with pagination ───────────────────────────────
        $allDisplayedServices = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? $telegramServices->where('id', (int) $serviceId)->values()
            : $telegramServices;

        $perPage = 25;
        $paginatedServices = new LengthAwarePaginator(
            $allDisplayedServices->forPage($page, $perPage),
            $allDisplayedServices->count(),
            $perPage,
            $page,
        );

        // Serialize services for JSON
        $servicesData = $paginatedServices->getCollection()->map(function ($service) use (
            $serviceStats, $accountsPerService, $delayPerService, $completionsTodayPerService
        ) {
            $stats     = $serviceStats[$service->id] ?? null;
            $compToday = (int) ($completionsTodayPerService[$service->id] ?? 0);

            return [
                'id'              => $service->id,
                'name'            => $service->name,
                'rate_per_1000'   => (float) $service->rate_per_1000,
                'accounts'        => (int) ($accountsPerService[$service->id] ?? 0),
                'delay_sec'       => $delayPerService[$service->id] ?? null,
                'completions_today' => $compToday,
                'income_today'    => $service->rate_per_1000 ? round($compToday * (float) $service->rate_per_1000 / 1000, 4) : 0,
                'in_progress_orders' => (int) ($stats->in_progress_orders ?? 0),
                'total_volume'    => (int) ($stats->total_volume ?? 0),
                'completed_orders'=> (int) ($stats->completed_orders ?? 0),
                'completed_balance' => (float) ($stats->completed_balance ?? 0),
            ];
        });

        // Totals across ALL services (not just current page)
        $totals = [
            'completions_today'  => $completionsTodayPerService->sum(),
            'accounts'           => $accountsTotal,
            'in_progress_orders' => $serviceStats->sum('in_progress_orders'),
            'total_volume'       => $serviceStats->sum('total_volume'),
            'income_today'       => $allDisplayedServices->sum(fn ($s) =>
                $s->rate_per_1000
                    ? ($completionsTodayPerService[$s->id] ?? 0) * (float) $s->rate_per_1000 / 1000
                    : 0
            ),
            'completed_orders'   => $serviceStats->sum('completed_orders'),
            'completed_balance'  => $serviceStats->sum('completed_balance'),
        ];

        return response()->json([
            // Top metrics
            'income_today'         => (float) $slowBlock['incomeToday'],
            'income_yesterday'     => (float) $slowBlock['incomeYesterday'],
            'completions_today'    => (int)   $slowBlock['completionsToday'],
            'completions_yesterday'=> (int)   $slowBlock['completionsYesterday'],

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
                'per_page'     => $perPage,
            ],
            'totals'            => $totals,

            // Meta
            'has_date_filter'            => $hasDateFilter,
            'completions_column_label'   => $hasDateFilter ? __('Completions') : __('Completions today'),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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
            'chart_labels' => [], 'chart_income' => [], 'chart_completions' => [],
            'period' => $period, 'has_date_filter' => false,
            'services' => [], 'telegramServices' => [], 'totals' => [],
            'pagination' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => 25],
            'completions_column_label' => __('Completions today'),
        ]);
    }
}
