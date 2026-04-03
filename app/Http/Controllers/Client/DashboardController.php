<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $client = Auth::guard('client')->user();
        $clientId = $client->id;

        $base = Order::query()->where('client_id', $clientId);

        $totalOrders = (clone $base)->count();

        $activeStatuses = [
            Order::STATUS_VALIDATING,
            Order::STATUS_AWAITING,
            Order::STATUS_PENDING,
            Order::STATUS_PENDING_DEPENDENCY,
            Order::STATUS_IN_PROGRESS,
            Order::STATUS_PROCESSING,
            Order::STATUS_PARTIAL,
        ];

        $activeOrdersCount = (clone $base)->whereIn('status', $activeStatuses)->count();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();
        $ordersThisMonth = (clone $base)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $spentThisMonth = (float) (clone $base)->whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('charge');

        $prevStart = now()->subMonth()->startOfMonth();
        $prevEnd = now()->subMonth()->endOfMonth();
        $ordersPrevMonth = (clone $base)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $spentPrevMonth = (float) (clone $base)->whereBetween('created_at', [$prevStart, $prevEnd])->sum('charge');

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

        $chartMonths = [];
        for ($i = 5; $i >= 0; $i--) {
            $d = now()->subMonths($i);
            $chartMonths[] = [
                'label' => $d->format('M'),
                'count' => (clone $base)
                    ->whereYear('created_at', $d->year)
                    ->whereMonth('created_at', $d->month)
                    ->count(),
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
            'contactTelegram' => ltrim((string) config('contact.telegram', ''), '@'),
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
