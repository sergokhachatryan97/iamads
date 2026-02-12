<?php

namespace App\Http\Controllers\Staff;

use App\Helpers\OrderQueryBuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkOrderActionRequest;
use App\Models\Order;
use App\Services\OrderServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private OrderServiceInterface $orderService
    ) {
    }

    /**
     * Display a listing of orders.
     * Staff members see only their assigned clients' orders.
     * Super admin sees all orders.
     */
    public function index(Request $request): View
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        $query = Order::with(['service', 'client', 'category', 'subscription', 'creator']);

        // Filter by staff member (unless super admin)
        if (!$isSuperAdmin) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            });
        }

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        // Validate sort direction
        if (!in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        // Validate sort column
        $allowedSortColumns = ['id', 'created_at', 'charge', 'quantity', 'status', 'remains'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        $ordersIds = $query->pluck('id')->toArray();

        $orders = $query->paginate(3)->withQueryString();

        // Get all available statuses for filter
        $statuses = [
            'all' => __('All Statuses'),
            Order::STATUS_AWAITING => __('Awaiting'),
            Order::STATUS_PENDING => __('Pending'),
            Order::STATUS_IN_PROGRESS => __('In Progress'),
            Order::STATUS_PROCESSING => __('Processing'),
            Order::STATUS_PARTIAL => __('Partial'),
            Order::STATUS_COMPLETED => __('Completed'),
            Order::STATUS_CANCELED => __('Canceled'),
            Order::STATUS_FAIL => __('Failed'),
        ];

        return view('staff.orders.index', [
            'orders' => $orders,
            'ordersIds' => $ordersIds,
            'statuses' => $statuses,
            'currentStatus' => $request->get('status', 'all'),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    /**
     * Cancel an order fully (awaiting/pending status).
     */
    public function cancelFull(Order $order): RedirectResponse
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        // Check if staff member has access to this order's client
        if (!$isSuperAdmin && $order->client->staff_id !== $user->id) {
            abort(403, 'You do not have permission to cancel this order.');
        }

        try {
            $this->orderService->cancelFull($order, $order->client);

            return redirect()
                ->route('staff.orders.index')
                ->with('success', 'Order canceled successfully. Refund has been processed.');
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.orders.index')
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()
                ->route('staff.orders.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an order partially (in_progress/processing status).
     */
    public function cancelPartial(Order $order): RedirectResponse
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        // Check if staff member has access to this order's client
        if (!$isSuperAdmin && $order->client->staff_id !== $user->id) {
            abort(403, 'You do not have permission to cancel this order.');
        }

        try {
            $this->orderService->cancelPartial($order, $order->client);

            return redirect()
                ->route('staff.orders.index')
                ->with('success', 'Order partially canceled. Refund for undelivered quantity has been processed.');
        } catch (ValidationException $e) {
            return redirect()
                ->route('staff.orders.index')
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()
                ->route('staff.orders.index')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Get eligible order IDs for bulk operations (for Select All).
     */
    public function getEligibleIds(Request $request): JsonResponse
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        $action = $request->get('action', 'cancel'); // 'cancel' or 'cancel-partial'

        $query = Order::query()
            ->with('service')
            ->whereHas('service', function ($q) {
                $q->where('user_can_cancel', true);
            });

        // Filter by staff member (unless super admin)
        if (!$isSuperAdmin) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            });
        }

        // Status filter from request
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by action type
        if ($action === 'cancel') {
            // Full cancel: awaiting, pending, processing
            $query->whereIn('status', [
                Order::STATUS_AWAITING,
                Order::STATUS_PENDING,
                Order::STATUS_PROCESSING,
            ]);
        } else {
            // Partial cancel: in_progress, processing
            $query->whereIn('status', [
                Order::STATUS_IN_PROGRESS,
                Order::STATUS_PROCESSING,
            ]);
        }

        $orderIds = $query->pluck('id')->toArray();

        return response()->json([
            'order_ids' => $orderIds,
            'count' => count($orderIds),
        ]);
    }

    /**
     * Handle bulk action (cancel_full, cancel_partial, refund).
     * Supports pagination-independent selection via select_all + filters.
     */
    public function bulkAction(BulkOrderActionRequest $request): RedirectResponse
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');
        $action = $request->input('action');
        $selectAll = $request->boolean('select_all');
        $selectedIds = $request->input('selected_ids', []);
        $excludedIds = $request->input('excluded_ids', []);
        $filters = $request->input('filters', []);

        // Build query based on selection mode
        $query = OrderQueryBuilder::buildBulkQuery(
            $selectAll,
            $selectedIds,
            $excludedIds,
            $filters,
            $isSuperAdmin,
            $user->id
        );

        // Count total matching orders
        $totalMatched = OrderQueryBuilder::countMatching($query);

        // Enforce max batch size
        if ($totalMatched > BulkOrderActionRequest::getMaxBatchSize()) {
            return redirect()
                ->route('staff.orders.index')
                ->with('error', "Cannot process more than " . BulkOrderActionRequest::getMaxBatchSize() . " orders at once. Please refine your filters.");
        }

        // Load orders with relationships, using chunkById for large batches
        $processedCount = 0;
        $succeededCount = 0;
        $failedCount = 0;
        $failures = [];

        $query->with(['service', 'client'])->chunkById(100, function ($orders) use ($action, &$processedCount, &$succeededCount, &$failedCount, &$failures) {
            foreach ($orders as $order) {
                $processedCount++;

                try {
                    // Validate order is eligible for the action
                    $service = $order->service;
                    if (!$service || !$service->user_can_cancel) {
                        $failures[] = [
                            'order_id' => $order->id,
                            'reason' => 'Service does not allow cancellation.',
                        ];
                        $failedCount++;
                        continue;
                    }

                    // Execute action
                    if ($action === 'cancel_full') {
                        // Full cancel: awaiting, pending, processing
                        if (!in_array($order->status, [Order::STATUS_AWAITING, Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
                            $failures[] = [
                                'order_id' => $order->id,
                                'reason' => "Cannot be fully canceled (status: {$order->status}).",
                            ];
                            $failedCount++;
                            continue;
                        }
                        $this->orderService->cancelFull($order, $order->client);
                    } elseif ($action === 'cancel_partial') {
                        // Partial cancel: in_progress, processing
                        if (!in_array($order->status, [Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING], true)) {
                            $failures[] = [
                                'order_id' => $order->id,
                                'reason' => "Cannot be partially canceled (status: {$order->status}).",
                            ];
                            $failedCount++;
                            continue;
                        }
                        $this->orderService->cancelPartial($order, $order->client);
                    } elseif ($action === 'refund') {
                        if (!in_array($order->status, [Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING], true)) {
                            $failures[] = [
                                'order_id' => $order->id,
                                'reason' => "Cannot be refunded (status: {$order->status}).",
                            ];
                            $failedCount++;
                            continue;
                        }
                        $this->orderService->cancelPartial($order, $order->client);
                    }

                    $succeededCount++;
                } catch (ValidationException $e) {
                    $errorMessages = implode(', ', array_merge(...array_values($e->errors())));
                    $failures[] = [
                        'order_id' => $order->id,
                        'reason' => $errorMessages ?: $e->getMessage(),
                    ];
                    $failedCount++;
                } catch (\Exception $e) {
                    $failures[] = [
                        'order_id' => $order->id,
                        'reason' => $e->getMessage(),
                    ];
                    $failedCount++;
                }
            }
        });

        // Build result message
        $actionLabels = [
            'cancel_full' => 'canceled (full refund)',
            'cancel_partial' => 'partially canceled',
            'refund' => 'refunded',
        ];
        $actionLabel = $actionLabels[$action] ?? 'processed';

        $message = '';
        if ($succeededCount > 0) {
            $message = "{$succeededCount} order(s) {$actionLabel} successfully.";
        }

        if ($failedCount > 0) {
            $message .= ($message ? ' ' : '') . "{$failedCount} order(s) failed to process.";
        }

        if ($processedCount === 0) {
            $message = 'No orders matched your selection criteria.';
        }

        $redirect = redirect()->route('staff.orders.index');

        if ($succeededCount > 0) {
            $redirect->with('success', $message);
        } else {
            $redirect->with('error', $message);
        }

        // Store detailed results in session
        if (!empty($failures)) {
            $redirect->with('bulk_result', [
                'total_matched' => $totalMatched,
                'processed_count' => $processedCount,
                'succeeded_count' => $succeededCount,
                'failed_count' => $failedCount,
                'failures' => $failures,
            ]);
        }

        return $redirect;
    }

    /**
     * Get order statuses for real-time updates.
     * Returns statuses for orders currently visible on the page.
     */
    public function getStatuses(Request $request): JsonResponse
    {
        $user = Auth::guard('staff')->user();
        $isSuperAdmin = $user->hasRole('super_admin');

        // Handle both GET (query params) and POST (body) requests
        $orderIds = $request->input('order_ids', []);

        // If empty, try to get from query string
        if (empty($orderIds) && $request->has('order_ids')) {
            $orderIds = $request->query('order_ids', []);
        }

        if (empty($orderIds) || !is_array($orderIds)) {
            return response()->json(['statuses' => []]);
        }

        // Validate order IDs are integers
        $orderIds = array_filter(array_map('intval', $orderIds));

        if (empty($orderIds)) {
            return response()->json(['statuses' => []]);
        }

        $query = Order::query()
            ->whereIn('id', $orderIds)
            ->select(['id', 'status', 'delivered', 'quantity', 'remains']);

        // Filter by staff member (unless super admin)
        if (!$isSuperAdmin) {
            $query->whereHas('client', function ($q) use ($user) {
                $q->where('staff_id', $user->id);
            });
        }

        $orders = $query->get();

        $statuses = [];
        foreach ($orders as $order) {
            $delivered = $order->delivered ?? 0;
            $quantity = $order->quantity ?? 1;
            $progress = $quantity > 0 ? round(($delivered / $quantity) * 100, 1) : 0;

            $statuses[$order->id] = [
                'status' => $order->status,
                'remains' => $order->remains,
                'delivered' => $delivered,
                'quantity' => $quantity,
                'progress' => $progress,
            ];
        }

        return response()->json(['statuses' => $statuses]);
    }
}
