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

class TelegramStatsController extends Controller
{
    private function yearMonthExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public function index(Request $request): View
    {
        $period = $request->input('period', 'days');
        if (!in_array($period, ['days', 'monthly'], true)) {
            $period = 'days';
        }

        // Date filter
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $hasDateFilter = $dateFrom || $dateTo;

        $dateFromParsed = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : null;
        $dateToParsed = $dateTo ? Carbon::parse($dateTo)->endOfDay() : null;

        // Service filter
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

        // Apply service filter to the IDs used in queries
        $filteredServiceIds = $serviceId && in_array((int) $serviceId, $telegramServiceIds)
            ? [(int) $serviceId]
            : $telegramServiceIds;

        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = now()->subDay()->format('Y-m-d');

        // === Top metrics ===
        if ($hasDateFilter) {
            // Custom date range: show totals for range, no comparison
            $incomeQuery = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->where('status', Order::STATUS_COMPLETED)
                ->whereNotNull('completed_at');
            if ($dateFromParsed) {
                $incomeQuery->where('completed_at', '>=', $dateFromParsed);
            }
            if ($dateToParsed) {
                $incomeQuery->where('completed_at', '<=', $dateToParsed);
            }
            $incomeToday = (float) $incomeQuery->sum(DB::raw('CAST(charge AS DECIMAL(20,4))'));
            $incomeYesterday = 0;

            $compQuery = DB::table('telegram_order_memberships')
                ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
                ->whereIn('orders.service_id', $filteredServiceIds)
                ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('telegram_order_memberships.subscribed_at');
            if ($dateFromParsed) {
                $compQuery->where('telegram_order_memberships.subscribed_at', '>=', $dateFromParsed);
            }
            if ($dateToParsed) {
                $compQuery->where('telegram_order_memberships.subscribed_at', '<=', $dateToParsed);
            }
            $completionsToday = (int) $compQuery->count();
            $completionsYesterday = 0;
        } else {
            // Default: today vs yesterday
            $incomeRows = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->where('status', Order::STATUS_COMPLETED)
                ->whereNotNull('completed_at')
                ->whereRaw('DATE(completed_at) IN (?, ?)', [$todayStr, $yesterdayStr])
                ->selectRaw('DATE(completed_at) as day, COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as income')
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $incomeToday = (float) ($incomeRows[$todayStr]->income ?? 0);
            $incomeYesterday = (float) ($incomeRows[$yesterdayStr]->income ?? 0);

            $completionRows = DB::table('telegram_order_memberships')
                ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
                ->whereIn('orders.service_id', $filteredServiceIds)
                ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('telegram_order_memberships.subscribed_at')
                ->whereRaw('DATE(telegram_order_memberships.subscribed_at) IN (?, ?)', [$todayStr, $yesterdayStr])
                ->selectRaw('DATE(telegram_order_memberships.subscribed_at) as day, COUNT(*) as completions')
                ->groupBy('day')
                ->get()
                ->keyBy('day');

            $completionsToday = (int) ($completionRows[$todayStr]->completions ?? 0);
            $completionsYesterday = (int) ($completionRows[$yesterdayStr]->completions ?? 0);
        }

        // === Chart data ===
        if ($hasDateFilter) {
            // Custom range: build day-by-day or month-by-month labels from the range
            $rangeStart = $dateFromParsed ?? Carbon::parse('2020-01-01');
            $rangeEnd = $dateToParsed ?? now()->endOfDay();
            $daysDiff = $rangeStart->diffInDays($rangeEnd);

            if ($daysDiff > 90) {
                // Use monthly grouping for large ranges
                $period = 'monthly';
                $incomeGroupExpr = $this->yearMonthExpr('completed_at');
                $membershipGroupExpr = $this->yearMonthExpr('telegram_order_memberships.subscribed_at');

                $cursor = $rangeStart->copy()->startOfMonth();
                $end = $rangeEnd->copy()->startOfMonth();
                $chartLabels = collect();
                while ($cursor->lte($end)) {
                    $chartLabels->push($cursor->format('Y-m'));
                    $cursor->addMonth();
                }
            } else {
                // Use daily grouping
                $incomeGroupExpr = 'DATE(completed_at)';
                $membershipGroupExpr = 'DATE(telegram_order_memberships.subscribed_at)';

                $cursor = $rangeStart->copy();
                $chartLabels = collect();
                while ($cursor->lte($rangeEnd)) {
                    $chartLabels->push($cursor->format('Y-m-d'));
                    $cursor->addDay();
                }
            }
        } elseif ($period === 'monthly') {
            $chartLabels = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
            $rangeStart = now()->subMonths(11)->startOfMonth();
            $rangeEnd = $todayEnd;
            $incomeGroupExpr = $this->yearMonthExpr('completed_at');
            $membershipGroupExpr = $this->yearMonthExpr('telegram_order_memberships.subscribed_at');

            // Override top metrics for monthly
            $currentMonthStr = now()->format('Y-m');
            $prevMonthStr = now()->subMonth()->format('Y-m');

            $monthlyIncome = DB::table('orders')
                ->whereIn('service_id', $filteredServiceIds)
                ->where('status', Order::STATUS_COMPLETED)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subMonth()->startOfMonth())
                ->selectRaw("{$this->yearMonthExpr('completed_at')} as period, COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as income")
                ->groupBy('period')
                ->get()
                ->keyBy('period');

            $monthlyCompletions = DB::table('telegram_order_memberships')
                ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
                ->whereIn('orders.service_id', $filteredServiceIds)
                ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
                ->whereNotNull('telegram_order_memberships.subscribed_at')
                ->where('telegram_order_memberships.subscribed_at', '>=', now()->subMonth()->startOfMonth())
                ->selectRaw("{$this->yearMonthExpr('telegram_order_memberships.subscribed_at')} as period, COUNT(*) as completions")
                ->groupBy('period')
                ->get()
                ->keyBy('period');

            $incomeToday = (float) ($monthlyIncome[$currentMonthStr]->income ?? 0);
            $incomeYesterday = (float) ($monthlyIncome[$prevMonthStr]->income ?? 0);
            $completionsToday = (int) ($monthlyCompletions[$currentMonthStr]->completions ?? 0);
            $completionsYesterday = (int) ($monthlyCompletions[$prevMonthStr]->completions ?? 0);
        } else {
            $chartLabels = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));
            $rangeStart = now()->subDays(6)->startOfDay();
            $rangeEnd = $todayEnd;
            $incomeGroupExpr = 'DATE(completed_at)';
            $membershipGroupExpr = 'DATE(telegram_order_memberships.subscribed_at)';
        }

        // Chart: income over time
        $chartIncomeRows = DB::table('orders')
            ->whereIn('service_id', $telegramServiceIds)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $rangeStart)
            ->where('completed_at', '<=', $rangeEnd)
            ->selectRaw("{$incomeGroupExpr} as period, COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as income")
            ->groupByRaw($incomeGroupExpr)
            ->get()
            ->keyBy('period');

        // Chart: completions (memberships) over time
        $chartCompletionRows = DB::table('telegram_order_memberships')
            ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
            ->whereIn('orders.service_id', $telegramServiceIds)
            ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNotNull('telegram_order_memberships.subscribed_at')
            ->where('telegram_order_memberships.subscribed_at', '>=', $rangeStart)
            ->where('telegram_order_memberships.subscribed_at', '<=', $rangeEnd)
            ->selectRaw("{$membershipGroupExpr} as period, COUNT(*) as completions")
            ->groupByRaw($membershipGroupExpr)
            ->get()
            ->keyBy('period');

        $chartIncomeData = $chartLabels->map(fn ($d) => (float) ($chartIncomeRows[$d]->income ?? 0))->values();
        $chartCompletionsData = $chartLabels->map(fn ($d) => (int) ($chartCompletionRows[$d]->completions ?? 0))->values();

        // === Per-service stats (scoped by date filter) ===
        $serviceStatsQuery = DB::table('orders')
            ->whereIn('service_id', $filteredServiceIds);
        if ($dateFromParsed) {
            $serviceStatsQuery->where('created_at', '>=', $dateFromParsed);
        }
        if ($dateToParsed) {
            $serviceStatsQuery->where('created_at', '<=', $dateToParsed);
        }
        $serviceStats = $serviceStatsQuery
            ->selectRaw("
                service_id,
                COUNT(*) as total_orders,
                COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) AND COALESCE(quantity, 0) > COALESCE(delivered, 0) THEN COALESCE(quantity, 0) - COALESCE(delivered, 0) ELSE 0 END), 0) as total_volume,
                COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_balance
            ", [
                Order::STATUS_IN_PROGRESS,
            ])
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        // Completions per service (scoped by date filter)
        $completionsPerServiceQuery = DB::table('telegram_order_memberships')
            ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
            ->whereIn('orders.service_id', $telegramServiceIds)
            ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNotNull('telegram_order_memberships.subscribed_at');

        if ($hasDateFilter) {
            if ($dateFromParsed) {
                $completionsPerServiceQuery->where('telegram_order_memberships.subscribed_at', '>=', $dateFromParsed);
            }
            if ($dateToParsed) {
                $completionsPerServiceQuery->where('telegram_order_memberships.subscribed_at', '<=', $dateToParsed);
            }
        } else {
            $completionsPerServiceQuery->whereRaw('DATE(telegram_order_memberships.subscribed_at) = ?', [$todayStr]);
        }

        $completionsTodayPerService = $completionsPerServiceQuery
            ->selectRaw('orders.service_id, COUNT(*) as completions_today')
            ->groupBy('orders.service_id')
            ->pluck('completions_today', 'service_id');

        // Accounts per service
        $accountsPerService = DB::table('telegram_order_memberships')
            ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
            ->whereIn('orders.service_id', $telegramServiceIds)
            ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->selectRaw('orders.service_id, COUNT(DISTINCT telegram_order_memberships.account_phone) as accounts_count')
            ->groupBy('orders.service_id')
            ->pluck('accounts_count', 'service_id');

        // Column header label
        $completionsColumnLabel = $hasDateFilter ? __('Completions') : __('Completions today');

        return view('staff.telegram-stats.index', compact(
            'incomeToday',
            'incomeYesterday',
            'completionsToday',
            'completionsYesterday',
            'chartLabels',
            'chartIncomeData',
            'chartCompletionsData',
            'telegramServices',
            'serviceStats',
            'completionsTodayPerService',
            'accountsPerService',
            'period',
            'dateFrom',
            'dateTo',
            'hasDateFilter',
            'serviceId',
            'completionsColumnLabel',
        ));
    }

    private function emptyView(string $period, ?string $dateFrom = null, ?string $dateTo = null, ?string $serviceId = null): View
    {
        return view('staff.telegram-stats.index', [
            'incomeToday' => 0,
            'incomeYesterday' => 0,
            'completionsToday' => 0,
            'completionsYesterday' => 0,
            'chartLabels' => collect(),
            'chartIncomeData' => collect(),
            'chartCompletionsData' => collect(),
            'telegramServices' => collect(),
            'serviceStats' => collect(),
            'completionsTodayPerService' => collect(),
            'accountsPerService' => collect(),
            'period' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'hasDateFilter' => (bool) ($dateFrom || $dateTo),
            'serviceId' => $serviceId,
            'completionsColumnLabel' => __('Completions today'),
        ]);
    }
}
