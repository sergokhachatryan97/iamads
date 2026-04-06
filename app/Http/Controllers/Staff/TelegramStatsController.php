<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Service;
use App\Models\TelegramOrderMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TelegramStatsController extends Controller
{
    /**
     * Year-month group expression compatible with both SQLite and MySQL.
     */
    private function yearMonthExpr(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }

        return "DATE_FORMAT({$column}, '%Y-%m')";
    }

    public function index(Request $request): View
    {
        $period = $request->input('period', 'days');
        if (!in_array($period, ['days', 'monthly'], true)) {
            $period = 'days';
        }

        $telegramServiceIds = Cache::remember('tg_stats:service_ids', 300, function () {
            $catIds = Category::where('link_driver', 'telegram')->pluck('id');

            return Service::whereIn('category_id', $catIds)->pluck('id')->all();
        });

        $telegramServices = Cache::remember('tg_stats:services', 300, function () use ($telegramServiceIds) {
            return Service::whereIn('id', $telegramServiceIds)->orderBy('priority')->get();
        });

        if (empty($telegramServiceIds)) {
            return $this->emptyView($period);
        }
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = now()->subDay()->format('Y-m-d');

        // === Top metrics: Income from orders, Completions from memberships ===

        // Income: today + yesterday
        $incomeRows = DB::table('orders')
            ->whereIn('service_id', $telegramServiceIds)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereRaw('DATE(completed_at) IN (?, ?)', [$todayStr, $yesterdayStr])
            ->selectRaw('DATE(completed_at) as day, COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as income')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $incomeToday = (float) ($incomeRows[$todayStr]->income ?? 0);
        $incomeYesterday = (float) ($incomeRows[$yesterdayStr]->income ?? 0);

        // Completions: count of subscribed memberships today + yesterday
        $completionRows = DB::table('telegram_order_memberships')
            ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
            ->whereIn('orders.service_id', $telegramServiceIds)
            ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNotNull('telegram_order_memberships.subscribed_at')
            ->whereRaw('DATE(telegram_order_memberships.subscribed_at) IN (?, ?)', [$todayStr, $yesterdayStr])
            ->selectRaw('DATE(telegram_order_memberships.subscribed_at) as day, COUNT(*) as completions')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $completionsToday = (int) ($completionRows[$todayStr]->completions ?? 0);
        $completionsYesterday = (int) ($completionRows[$yesterdayStr]->completions ?? 0);

        // === Chart data ===
        if ($period === 'monthly') {
            $chartLabels = collect(range(11, 0))->map(fn ($i) => now()->subMonths($i)->format('Y-m'));
            $rangeStart = now()->subMonths(11)->startOfMonth();
            $incomeGroupExpr = $this->yearMonthExpr('completed_at');
            $membershipGroupExpr = $this->yearMonthExpr('telegram_order_memberships.subscribed_at');

            // Override top metrics for monthly: current month vs previous month
            $currentMonthStr = now()->format('Y-m');
            $prevMonthStr = now()->subMonth()->format('Y-m');

            $monthlyIncome = DB::table('orders')
                ->whereIn('service_id', $telegramServiceIds)
                ->where('status', Order::STATUS_COMPLETED)
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subMonth()->startOfMonth())
                ->selectRaw("{$this->yearMonthExpr('completed_at')} as period, COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as income")
                ->groupBy('period')
                ->get()
                ->keyBy('period');

            $monthlyCompletions = DB::table('telegram_order_memberships')
                ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
                ->whereIn('orders.service_id', $telegramServiceIds)
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
            $incomeGroupExpr = 'DATE(completed_at)';
            $membershipGroupExpr = 'DATE(telegram_order_memberships.subscribed_at)';
        }

        // Chart: income over time
        $chartIncomeRows = DB::table('orders')
            ->whereIn('service_id', $telegramServiceIds)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $rangeStart)
            ->where('completed_at', '<=', $todayEnd)
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
            ->where('telegram_order_memberships.subscribed_at', '<=', $todayEnd)
            ->selectRaw("{$membershipGroupExpr} as period, COUNT(*) as completions")
            ->groupByRaw($membershipGroupExpr)
            ->get()
            ->keyBy('period');

        $chartIncomeData = $chartLabels->map(fn ($d) => (float) ($chartIncomeRows[$d]->income ?? 0))->values();
        $chartCompletionsData = $chartLabels->map(fn ($d) => (int) ($chartCompletionRows[$d]->completions ?? 0))->values();

        // === Per-service stats ===
        $serviceStats = DB::table('orders')
            ->whereIn('service_id', $telegramServiceIds)
            ->selectRaw("
                service_id,
                COUNT(*) as total_orders,
                COALESCE(SUM(CASE WHEN status IN (?, ?, ?, ?) AND COALESCE(quantity, 0) > COALESCE(delivered, 0) THEN COALESCE(quantity, 0) - COALESCE(delivered, 0) ELSE 0 END), 0) as total_volume,
                COALESCE(SUM(CAST(charge AS DECIMAL(20,4))), 0) as total_balance
            ", [
                Order::STATUS_PENDING, Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING, Order::STATUS_AWAITING,
            ])
            ->groupBy('service_id')
            ->get()
            ->keyBy('service_id');

        // Completions today per service (subscribed memberships)
        $completionsTodayPerService = DB::table('telegram_order_memberships')
            ->join('orders', 'orders.id', '=', 'telegram_order_memberships.order_id')
            ->whereIn('orders.service_id', $telegramServiceIds)
            ->where('telegram_order_memberships.state', TelegramOrderMembership::STATE_SUBSCRIBED)
            ->whereNotNull('telegram_order_memberships.subscribed_at')
            ->whereRaw('DATE(telegram_order_memberships.subscribed_at) = ?', [$todayStr])
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
        ));
    }

    private function emptyView(string $period): View
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
        ]);
    }
}
