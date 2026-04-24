<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TelegramOrderMembership;
use App\Models\TelegramTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FailedTelegramTasksController extends Controller
{
    private function authorizeSuperAdmin(): void
    {
        $user = Auth::guard('staff')->user();
        if (!$user || !$user->hasRole('super_admin')) {
            abort(403);
        }
    }

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin();

        $tab = $request->get('tab', 'tasks');
        $filterStatus = $request->get('order_status', 'all');
        $filterOrderId = $request->get('order_id');

        if ($tab === 'memberships') {
            $query = TelegramOrderMembership::query()
                ->select('order_id', DB::raw('COUNT(*) as failed_count'), DB::raw('MAX(updated_at) as last_failed_at'))
                ->where('state', TelegramOrderMembership::STATE_FAILED)
                ->groupBy('order_id');

            if ($filterOrderId) {
                $query->where('order_id', $filterOrderId);
            }
            if ($filterStatus !== 'all') {
                $query->whereIn('order_id', Order::where('status', $filterStatus)->select('id'));
            }

            $grouped = $query->orderByDesc('last_failed_at')->paginate(30)->withQueryString();

            $orderIds = $grouped->pluck('order_id')->toArray();
            $orders = Order::with('service:id,name')->whereIn('id', $orderIds)->get(['id', 'service_id', 'status'])->keyBy('id');

            $errors = $this->loadRecentMembershipErrors($orderIds);
        } else {
            $query = TelegramTask::query()
                ->select('order_id', DB::raw('COUNT(*) as failed_count'), DB::raw('MAX(updated_at) as last_failed_at'))
                ->where('status', TelegramTask::STATUS_FAILED)
                ->groupBy('order_id');

            if ($filterOrderId) {
                $query->where('order_id', $filterOrderId);
            }
            if ($filterStatus !== 'all') {
                $query->whereIn('order_id', Order::where('status', $filterStatus)->select('id'));
            }

            $grouped = $query->orderByDesc('last_failed_at')->paginate(30)->withQueryString();

            $orderIds = $grouped->pluck('order_id')->toArray();
            $orders = Order::with('service:id,name')->whereIn('id', $orderIds)->get(['id', 'service_id', 'status'])->keyBy('id');

            $errors = $this->loadRecentTaskErrors($orderIds);
        }

        // Combined counts in 2 queries instead of 4
        $taskCounts = DB::table('telegram_tasks')
            ->where('status', TelegramTask::STATUS_FAILED)
            ->selectRaw('COUNT(*) as total, COUNT(DISTINCT order_id) as order_count')
            ->first();
        $membershipCounts = DB::table('telegram_order_memberships')
            ->where('state', TelegramOrderMembership::STATE_FAILED)
            ->selectRaw('COUNT(*) as total, COUNT(DISTINCT order_id) as order_count')
            ->first();

        $taskCount = (int) $taskCounts->total;
        $taskOrderCount = (int) $taskCounts->order_count;
        $membershipCount = (int) $membershipCounts->total;
        $membershipOrderCount = (int) $membershipCounts->order_count;

        // Error summary — group by error type
        if ($tab === 'memberships') {
            $errorSummary = DB::table('telegram_order_memberships')
                ->where('state', TelegramOrderMembership::STATE_FAILED)
                ->whereNotNull('last_error')
                ->where('last_error', '!=', '')
                ->select('last_error as error_key', DB::raw('COUNT(*) as error_count'), DB::raw('COUNT(DISTINCT order_id) as order_count'))
                ->groupBy('last_error')
                ->orderByDesc('error_count')
                ->limit(30)
                ->get();
        } else {
            $errorSummary = DB::table('telegram_tasks')
                ->where('status', TelegramTask::STATUS_FAILED)
                ->select('action as error_key', DB::raw('COUNT(*) as error_count'), DB::raw('COUNT(DISTINCT order_id) as order_count'))
                ->groupBy('action')
                ->orderByDesc('error_count')
                ->limit(30)
                ->get();
        }

        return view('staff.failed-telegram-tasks.index', compact(
            'grouped', 'orders', 'errors', 'tab',
            'taskCount', 'membershipCount',
            'taskOrderCount', 'membershipOrderCount',
            'filterOrderId', 'filterStatus',
            'errorSummary'
        ));
    }

    private function loadRecentTaskErrors(array $orderIds): \Illuminate\Support\Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $ids = implode(',', array_map('intval', $orderIds));
        $status = TelegramTask::STATUS_FAILED;

        $rows = DB::select("SELECT order_id, result, action, updated_at FROM (SELECT order_id, result, action, updated_at, ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY updated_at DESC) as rn FROM telegram_tasks WHERE status = ? AND order_id IN ({$ids}) AND result IS NOT NULL) as ranked WHERE rn <= 3", [$status]);

        return collect($rows)->groupBy('order_id')->map(function ($items) {
            return $items->map(function ($item) {
                $item->result = json_decode($item->result, true);
                $item->updated_at = \Carbon\Carbon::parse($item->updated_at);
                return $item;
            });
        });
    }

    private function loadRecentMembershipErrors(array $orderIds): \Illuminate\Support\Collection
    {
        if (empty($orderIds)) {
            return collect();
        }

        $ids = implode(',', array_map('intval', $orderIds));
        $state = TelegramOrderMembership::STATE_FAILED;

        $rows = DB::select("SELECT order_id, last_error, account_phone, updated_at FROM (SELECT order_id, last_error, account_phone, updated_at, ROW_NUMBER() OVER (PARTITION BY order_id ORDER BY updated_at DESC) as rn FROM telegram_order_memberships WHERE state = ? AND order_id IN ({$ids}) AND last_error IS NOT NULL AND last_error != '') as ranked WHERE rn <= 3", [$state]);

        return collect($rows)->groupBy('order_id')->map(function ($items) {
            return $items->map(function ($item) {
                $item->updated_at = \Carbon\Carbon::parse($item->updated_at);
                return $item;
            });
        });
    }

    public function deleteCompletedOrderTasks(): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $deleted = DB::transaction(function () {
            $tasks = TelegramTask::where('status', TelegramTask::STATUS_FAILED)
                ->whereIn('order_id', Order::where('status', 'completed')->select('id'))
                ->delete();

            $memberships = TelegramOrderMembership::where('state', TelegramOrderMembership::STATE_FAILED)
                ->whereIn('order_id', Order::where('status', 'completed')->select('id'))
                ->delete();

            return $tasks + $memberships;
        });

        return back()->with('success', __(':count failed records for completed orders deleted.', ['count' => $deleted]));
    }

    public function deleteByOrder(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        $orderId = $request->input('order_id');
        if (!$orderId) {
            return back()->with('error', __('Order ID required.'));
        }

        $deleted = DB::transaction(function () use ($orderId) {
            $tasks = TelegramTask::where('order_id', $orderId)
                ->where('status', TelegramTask::STATUS_FAILED)
                ->delete();

            $memberships = TelegramOrderMembership::where('order_id', $orderId)
                ->where('state', TelegramOrderMembership::STATE_FAILED)
                ->delete();

            return $tasks + $memberships;
        });

        return back()->with('success', __(':count failed records for order #:id deleted.', ['count' => $deleted, 'id' => $orderId]));
    }
}
