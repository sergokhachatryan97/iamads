<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $client = Auth::guard('client')->user();
        $clientId = $client->id;

        $base = Order::query()->where('client_id', $clientId);

        $activeStatuses = [
            Order::STATUS_VALIDATING,
            Order::STATUS_AWAITING,
            Order::STATUS_PENDING,
            Order::STATUS_PENDING_DEPENDENCY,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIAL,
        ];

        // Single query for total, active, this month, prev month counts + spending
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $prevStart = now()->subMonth()->startOfMonth();
        $prevEnd = now()->subMonth()->endOfMonth();

        $stats = DB::table('orders')
            ->where('client_id', $clientId)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status IN (?,?,?,?,?,?,?) THEN 1 ELSE 0 END) as active_orders,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_this_month,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END) as spent_this_month,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as orders_prev_month,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN CAST(charge AS DECIMAL(20,4)) ELSE 0 END) as spent_prev_month
            ', [
                ...$activeStatuses,
                $startOfMonth, $endOfMonth,
                $startOfMonth, $endOfMonth,
                $prevStart, $prevEnd,
                $prevStart, $prevEnd,
            ])
            ->first();

        $totalOrders = (int) $stats->total_orders;
        $activeOrdersCount = (int) $stats->active_orders;
        $ordersThisMonth = (int) $stats->orders_this_month;
        $spentThisMonth = (float) $stats->spent_this_month;
        $ordersPrevMonth = (int) $stats->orders_prev_month;
        $spentPrevMonth = (float) $stats->spent_prev_month;

        $ordersTrendPct = $this->percentChange($ordersPrevMonth, $ordersThisMonth);
        $spentTrendPct = $this->percentChange($spentPrevMonth, $spentThisMonth);

        $recentOrders = (clone $base)
            ->with(['service', 'category'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $platformStats = Order::query()
            ->where('orders.client_id', $clientId)
            ->whereNotNull('orders.category_id')
            ->join('categories', 'orders.category_id', '=', 'categories.id')
            ->selectRaw('categories.name as platform_name, COUNT(orders.id) as cnt')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get();

        // Single query for 6-month chart (was 6 separate queries)
        $sixMonthsAgo = now()->subMonths(5)->startOfMonth();
        $monthExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyCounts = DB::table('orders')
            ->where('client_id', $clientId)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->selectRaw("{$monthExpr} as month, COUNT(*) as cnt")
            ->groupByRaw($monthExpr)
            ->pluck('cnt', 'month');

        $chartMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $key = $d->format('Y-m');
            $chartMonths[] = [
                'label' => $d->format('M'),
                'count' => (int) ($monthlyCounts[$key] ?? 0),
            ];
        }
        $chartMax = max(1, ...array_column($chartMonths, 'count'));

        return view('client.dashboard', [
            'client' => $client,
            'totalOrders' => $totalOrders,
            'activeOrdersCount' => $activeOrdersCount,
            'ordersThisMonth' => $ordersThisMonth,
            'spentThisMonth' => $spentThisMonth,
            'ordersTrendPct' => $ordersTrendPct,
            'spentTrendPct' => $spentTrendPct,
            'recentOrders' => $recentOrders,
            'platformStats' => $platformStats,
            'chartMonths' => $chartMonths,
            'chartMax' => $chartMax,
            'contactTelegram' => \App\Helpers\ContactHelper::telegram(),
        ]);
    }

    /**
     * @param  float|int  $previous
     * @param  float|int  $current
     */
    private function percentChange($previous, $current): ?int
    {
        if ($previous <= 0) {
            return null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
