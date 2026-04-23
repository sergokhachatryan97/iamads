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
            $orders = Order::with('service')->whereIn('id', $orderIds)->get()->keyBy('id');

            // Load recent errors per order (last 3)
            $errors = collect();
            if (!empty($orderIds)) {
                $errors = TelegramOrderMembership::where('state', TelegramOrderMembership::STATE_FAILED)
                    ->whereIn('order_id', $orderIds)
                    ->whereNotNull('last_error')
                    ->where('last_error', '!=', '')
                    ->orderByDesc('updated_at')
                    ->get(['order_id', 'last_error', 'account_phone', 'updated_at'])
                    ->groupBy('order_id')
                    ->map(fn($items) => $items->take(3));
            }
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
            $orders = Order::with('service')->whereIn('id', $orderIds)->get()->keyBy('id');

            // Load recent errors per order (last 3)
            $errors = collect();
            if (!empty($orderIds)) {
                $errors = TelegramTask::where('status', TelegramTask::STATUS_FAILED)
                    ->whereIn('order_id', $orderIds)
                    ->whereNotNull('result')
                    ->orderByDesc('updated_at')
                    ->get(['order_id', 'result', 'action', 'updated_at'])
                    ->groupBy('order_id')
                    ->map(fn($items) => $items->take(3));
            }
        }

        $taskCount = TelegramTask::where('status', TelegramTask::STATUS_FAILED)->count();
        $membershipCount = TelegramOrderMembership::where('state', TelegramOrderMembership::STATE_FAILED)->count();
        $taskOrderCount = TelegramTask::where('status', TelegramTask::STATUS_FAILED)->distinct('order_id')->count('order_id');
        $membershipOrderCount = TelegramOrderMembership::where('state', TelegramOrderMembership::STATE_FAILED)->distinct('order_id')->count('order_id');

        // Error summary — group by error type
        if ($tab === 'memberships') {
            // Group by last_error text
            $errorSummary = TelegramOrderMembership::where('state', TelegramOrderMembership::STATE_FAILED)
                ->whereNotNull('last_error')
                ->where('last_error', '!=', '')
                ->select('last_error as error_key', DB::raw('COUNT(*) as error_count'), DB::raw('COUNT(DISTINCT order_id) as order_count'))
                ->groupBy('last_error')
                ->orderByDesc('error_count')
                ->limit(30)
                ->get();
        } else {
            // Group by action type
            $errorSummary = TelegramTask::where('status', TelegramTask::STATUS_FAILED)
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
