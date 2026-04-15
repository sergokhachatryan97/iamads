<?php

namespace App\Http\Controllers\Client;

use App\Helpers\OrderQueryBuilder;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientBulkOrderActionRequest;
use App\Http\Requests\MultiStoreOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use App\Services\CategoryServiceInterface;
use App\Services\OrderServiceInterface;
use App\Services\YouTube\YouTubeExecutionPlanResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OrderController extends Controller
{
    protected Client $client;

    public function __construct(
        private OrderServiceInterface $orderService,
        private CategoryServiceInterface $categoryService
    ) {
        $this->client = Auth::guard('client')->user();
    }

    /**
     * Display a listing of the client's orders.
     */
    public function index(Request $request): View
    {
        $query = Order::where('client_id', $this->client->id)->with(['service', 'category']);

        // Status filter
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Source filter (web / api)
        if ($request->filled('source') && in_array($request->source, ['web', 'api'], true)) {
            $query->where('source', $request->source);
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Service filter
        if ($request->filled('service_id')) {
            $query->where('service_id', $request->service_id);
        }

        // Search filter (URL or order ID - single field)
        if ($request->filled('search')) {
            $search = trim($request->search);
            if (is_numeric($search)) {
                $query->where('id', (int) $search);
            } else {
                $query->where('link', 'like', '%'.addcslashes($search, '%_\\').'%');
            }
        } elseif ($request->filled('link')) {
            $query->where('link', 'like', '%'.addcslashes($request->link, '%_\\').'%');
        } elseif ($request->filled('order_id')) {
            $orderId = trim($request->order_id);
            if (is_numeric($orderId)) {
                $query->where('id', (int) $orderId);
            } else {
                $query->whereRaw('CAST(id AS CHAR) LIKE ?', ['%'.addcslashes($orderId, '%_\\').'%']);
            }
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');

        // Validate sort direction
        if (! in_array($sortDir, ['asc', 'desc'])) {
            $sortDir = 'desc';
        }

        // Validate sort column
        $allowedSortColumns = ['id', 'created_at', 'charge', 'quantity', 'status', 'remains'];
        if (! in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'created_at';
        }

        $query->orderBy($sortBy, $sortDir);

        $ordersIds = (clone $query)->pluck('id')->toArray();

        $orders = $query->paginate(20)->withQueryString();

        // Per-status counts for this client (respect filters)
        $countQuery = Order::where('client_id', $this->client->id);
        if ($request->filled('source') && in_array($request->source, ['web', 'api'], true)) {
            $countQuery->where('source', $request->source);
        }
        if ($request->filled('category_id')) {
            $countQuery->where('category_id', $request->category_id);
        }
        if ($request->filled('service_id')) {
            $countQuery->where('service_id', $request->service_id);
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            if (is_numeric($search)) {
                $countQuery->where('id', (int) $search);
            } else {
                $countQuery->where('link', 'like', '%'.addcslashes($search, '%_\\').'%');
            }
        } elseif ($request->filled('link')) {
            $countQuery->where('link', 'like', '%'.addcslashes($request->link, '%_\\').'%');
        } elseif ($request->filled('order_id')) {
            $orderId = trim($request->order_id);
            if (is_numeric($orderId)) {
                $countQuery->where('id', (int) $orderId);
            } else {
                $countQuery->whereRaw('CAST(id AS CHAR) LIKE ?', ['%'.addcslashes($orderId, '%_\\').'%']);
            }
        }
        if ($request->filled('date_from')) {
            $countQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $countQuery->whereDate('created_at', '<=', $request->date_to);
        }
        $statusCounts = $countQuery
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        // Get all available statuses for filter
        $statuses = [
            'all' => __('All Statuses'),
            Order::STATUS_VALIDATING => __('Validating'),
            Order::STATUS_AWAITING => __('Awaiting'),
            Order::STATUS_IN_PROGRESS => __('In Progress'),
            Order::STATUS_PROCESSING => __('Processing'),
            Order::STATUS_PARTIAL => __('Partial'),
            Order::STATUS_COMPLETED => __('Completed'),
            Order::STATUS_CANCELED => __('Canceled'),
            Order::STATUS_FAIL => __('Failed'),
        ];

        $categories = $this->categoryService->getAllCategories(true);
        $services = Service::query()
            ->where('is_active', true)
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id))
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return view('client.orders.index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'statusCounts' => $statusCounts,
            'currentStatus' => ($request->filled('status') && $request->status !== 'all') ? $request->status : 'all',
            'currentSource' => $request->get('source', 'all'),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'categories' => $categories,
            'services' => $services,
            'filterCategoryId' => $request->get('category_id'),
            'filterServiceId' => $request->get('service_id'),
            'filterDateFrom' => $request->get('date_from'),
            'filterDateTo' => $request->get('date_to'),
            'filterSearch' => $request->get('search', $request->get('link', $request->get('order_id'))),
            'ordersIds' => $ordersIds,
        ]);
    }

    /**
     * Bulk cancel (full or partial) for the authenticated client's orders only.
     */
    public function bulkAction(ClientBulkOrderActionRequest $request): RedirectResponse
    {
        $client = $this->client;
        $action = $request->input('action');
        $selectAll = $request->boolean('select_all');
        $selectedIds = $request->input('selected_ids', []);
        $excludedIds = $request->input('excluded_ids', []);
        $filters = $request->input('filters', []);

        $query = OrderQueryBuilder::buildBulkQuery(
            $selectAll,
            $selectedIds,
            $excludedIds,
            $filters,
            null,
            null,
            $client->id
        );

        $totalMatched = OrderQueryBuilder::countMatching($query);

        if ($totalMatched > ClientBulkOrderActionRequest::getMaxBatchSize()) {
            return redirect()
                ->route('client.orders.index')
                ->with('error', __('Cannot process more than :count orders at once. Please refine your filters.', ['count' => ClientBulkOrderActionRequest::getMaxBatchSize()]));
        }

        $processedCount = 0;
        $succeededCount = 0;
        $failedCount = 0;
        $failures = [];

        $query->with(['service'])->chunkById(100, function ($orders) use ($action, &$processedCount, &$succeededCount, &$failedCount, &$failures, $client) {
            foreach ($orders as $order) {
                $processedCount++;

                try {
                    if ((int) $order->client_id !== (int) $client->id) {
                        $failedCount++;
                        $failures[] = [
                            'order_id' => $order->id,
                            'reason' => __('Access denied.'),
                        ];

                        continue;
                    }

                    $service = $order->service;
                    if (! $service || ! $service->user_can_cancel) {
                        $failures[] = [
                            'order_id' => $order->id,
                            'reason' => __('Service does not allow cancellation.'),
                        ];
                        $failedCount++;

                        continue;
                    }

                    if ($action === 'cancel_full') {
                        if (! in_array($order->status, [Order::STATUS_AWAITING, Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
                            $failures[] = [
                                'order_id' => $order->id,
                                'reason' => __('Cannot be fully canceled (status: :status).', ['status' => $order->status]),
                            ];
                            $failedCount++;

                            continue;
                        }
                        $this->orderService->cancelFull($order, $client);
                    } elseif ($action === 'cancel_partial') {
                        if (! in_array($order->status, [Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING], true)) {
                            $failures[] = [
                                'order_id' => $order->id,
                                'reason' => __('Cannot be partially canceled (status: :status).', ['status' => $order->status]),
                            ];
                            $failedCount++;

                            continue;
                        }
                        $this->orderService->cancelPartial($order, $client);
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

        $actionLabel = $action === 'cancel_full'
            ? __('canceled (full refund)')
            : __('partially canceled');

        $message = '';
        if ($succeededCount > 0) {
            $message = __(':count order(s) :action successfully.', ['count' => $succeededCount, 'action' => $actionLabel]);
        }

        if ($failedCount > 0) {
            $message .= ($message !== '' ? ' ' : '').__(':count order(s) failed to process.', ['count' => $failedCount]);
        }

        if ($processedCount === 0) {
            $message = __('No orders matched your selection criteria.');
        }

        $redirect = redirect()->back();

        if ($succeededCount > 0) {
            $redirect->with('success', $message);
        } else {
            $redirect->with('error', $message);
        }

        if (! empty($failures)) {
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
     * Show the form for creating a new order.
     */
    public function create(Request $request): View
    {
        $categories = $this->categoryService->getAllCategories(true);

        // Categories that show Target Type (e.g. Telegram) – by link_driver
        $categoryIdsWithTargetType = collect($categories)->filter(function ($c) {
            return ($c->link_driver ?? 'generic') === 'telegram';
        })->pluck('id')->values()->all();

        // Link driver per category for frontend validation (telegram, youtube, tiktok, instagram, facebook, url, generic)
        $categoryLinkTypes = collect($categories)->mapWithKeys(function ($c) {
            return [(int) $c->id => $c->link_driver ?? 'generic'];
        })->all();

        // Get pre-selected category and service from query parameters
        $preselectedCategoryId = $request->get('category_id');
        $preselectedServiceId = $request->get('service_id');
        $preselectedTargetType = $request->get('target_type');

        return view('client.orders.create', [
            'categories' => $categories,
            'categoryIdsWithTargetType' => $categoryIdsWithTargetType,
            'categoryLinkTypes' => $categoryLinkTypes,
            'preselectedCategoryId' => $preselectedCategoryId,
            'preselectedServiceId' => $preselectedServiceId,
            'preselectedTargetType' => $preselectedTargetType,
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {

        try {
            $orders = $this->orderService->create($this->client, $request->payload());

            return redirect()
                ->route('client.orders.success')
                ->with('created_order_ids', $orders->pluck('id')->all());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'error' => 'Something went wrong. Please try again.',
            ])->withInput();
        }
    }

    /**
     * Store a multi-service order (creates multiple orders for one link).
     */
    public function multiStore(MultiStoreOrderRequest $request): RedirectResponse
    {

        try {
            $orders = $this->orderService->createMultiServiceOrders($this->client, $request->payload());

            return redirect()
                ->route('client.orders.success')
                ->with('created_order_ids', $orders->pluck('id')->all());
        } catch (ValidationException $e) {
            if ($e->validator->errors()->has('balance')) {
                return redirect()->route('client.balance.add', ['return_to' => 'order'])
                    ->with('info', __('Insufficient balance. Please add funds to complete your order.'));
            }

            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'error' => 'Something went wrong. Please try again.',
            ])->withInput();
        }
    }

    /**
     * Show order creation success page.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $orderIds = session('created_order_ids', []);

        if (empty($orderIds)) {
            return redirect()->route('client.orders.index');
        }

        $orders = Order::with('service')
            ->where('client_id', $this->client->id)
            ->whereIn('id', $orderIds)
            ->get();

        if ($orders->isEmpty()) {
            return redirect()->route('client.orders.index');
        }

        $totalCharge = $orders->sum('charge');
        $isSingle = $orders->count() === 1;

        return view('client.orders.success', [
            'orders' => $orders,
            'totalCharge' => $totalCharge,
            'isSingle' => $isSingle,
        ]);
    }

    /**
     * Get services by category (JSON endpoint for dynamic dropdown).
     */
    public function servicesByCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'target_type' => ['nullable', 'string', 'in:bot,channel,group,app,youtube'],
        ]);

        $client = $this->client;

        $categoryId = (int) $validated['category_id'];
        $targetType = $validated['target_type'] ?? null;

        // Normalize client rates keys to string once (avoids int/string duplication checks)
        $clientRatesRaw = is_array($client->rates) ? $client->rates : [];
        $clientRates = [];
        foreach ($clientRatesRaw as $k => $v) {
            $clientRates[(string) $k] = $v;
        }

        $clientDiscount = (float) ($client->discount ?? 0);

        // Load all limits once and key by service_id
        $serviceLimits = $client->serviceLimits()
            ->select(['service_id', 'min_quantity', 'max_quantity', 'increment'])
            ->get()
            ->keyBy('service_id');

        $pricingService = app(\App\Services\PricingService::class);

        $category = Category::find($categoryId);
        $linkDriver = $category?->link_driver ?? 'generic';

        $query = Service::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true);

        // Filter by target_type if provided
        if ($targetType) {
            $query->where('target_type', $targetType);
        }

        $services = $query->orderBy('name')
            ->get([
                'id',
                'name',
                'min_quantity',
                'max_quantity',
                'rate_per_1000',
                'increment',
                'deny_link_duplicates',
                'deny_duplicates_days',
                'service_cost_per_1000',
                'is_active',
                'service_type',
                'dripfeed_enabled',
                'speed_limit_enabled',
                'speed_limit_tier_mode',
                'speed_multiplier_fast',
                'speed_multiplier_super_fast',
                'target_type',
                'template_key',
            ])
            ->map(function (Service $service) use ($clientRates, $serviceLimits, $client, $pricingService, $clientDiscount, $linkDriver) {

                $serviceIdKey = (string) $service->id;

                // Custom rate (if set on client->rates)
                $customRate = null;
                $rateData = $clientRates[$serviceIdKey] ?? null;
                if (is_array($rateData) && isset($rateData['type'], $rateData['value'])) {
                    $customRate = [
                        'type' => (string) $rateData['type'],
                        'value' => (float) $rateData['value'],
                    ];
                }

                // Custom limits (if set)
                $limit = $serviceLimits->get($service->id);
                $hasCustomLimit = (bool) $limit;

                $effectiveMinQty = $hasCustomLimit && $limit->min_quantity !== null
                    ? (int) $limit->min_quantity
                    : (int) $service->min_quantity;

                $effectiveMaxQty = $hasCustomLimit && $limit->max_quantity !== null
                    ? (int) $limit->max_quantity
                    : ($service->max_quantity !== null ? (int) $service->max_quantity : null);

                $effectiveIncrement = $hasCustomLimit && $limit->increment !== null
                    ? (int) $limit->increment
                    : (int) ($service->increment ?? 0);

                // Effective rate (PricingService handles custom + discount rules)
                $defaultRate = (float) $service->rate_per_1000;
                $effectiveRate = (float) $pricingService->priceForClient($service, $client);

                // Only for UI: show whether discount applies (usually only if no custom rate)
                $discountApplies = ($customRate === null) && $clientDiscount > 0;

                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'target_type' => $service->target_type,
                    'template_key' => $service->template_key,

                    'min_quantity' => $effectiveMinQty,
                    'max_quantity' => $effectiveMaxQty,
                    'increment' => $effectiveIncrement,

                    'rate_per_1000' => $effectiveRate,
                    'deny_link_duplicates' => (bool) ($service->deny_link_duplicates ?? false),
                    'deny_duplicates_days' => (int) ($service->deny_duplicates_days ?? 90),
                    'service_cost_per_1000' => $service->service_cost_per_1000 !== null ? (float) $service->service_cost_per_1000 : null,
                    'is_active' => (bool) ($service->is_active ?? true),

                    // Originals for display
                    'default_min_quantity' => (int) $service->min_quantity,
                    'default_max_quantity' => $service->max_quantity !== null ? (int) $service->max_quantity : null,
                    'default_rate_per_1000' => $defaultRate,
                    'default_increment' => (int) ($service->increment ?? 0),

                    // Overrides info
                    'has_custom_rate' => $customRate !== null,
                    'custom_rate' => $customRate,

                    'has_custom_limit' => $hasCustomLimit,
                    'custom_limit' => $hasCustomLimit ? [
                        'min_quantity' => $limit->min_quantity,
                        'max_quantity' => $limit->max_quantity,
                        'increment' => $limit->increment,
                    ] : null,

                    // Discount info
                    'client_discount' => $clientDiscount,
                    'discount_applies' => $discountApplies,

                    // Service type and dripfeed
                    'service_type' => $service->service_type ?? null,
                    'dripfeed_enabled' => (bool) ($service->dripfeed_enabled ?? false),

                    // Speed limit settings
                    'speed_limit_enabled' => (bool) ($service->speed_limit_enabled ?? false),
                    'speed_limit_tier_mode' => $service->speed_limit_tier_mode ?? 'fast',
                    'speed_multiplier_fast' => $service->speed_multiplier_fast !== null ? (float) $service->speed_multiplier_fast : 1.50,
                    'speed_multiplier_super_fast' => $service->speed_multiplier_super_fast !== null ? (float) $service->speed_multiplier_super_fast : 2,

                    // YouTube watch-time (yt_watch_time): client chooses seconds per order
                    'youtube_requires_watch_time' => (bool) (config("youtube_service_templates.{$service->template_key}.requires_watch_time") ?? false),
                    'default_watch_time_seconds' => max(1, (int) (
                        $service->watch_time_seconds
                        ?? config("youtube_service_templates.{$service->template_key}.default_watch_time_seconds")
                        ?? config('youtube.default_watch_time_seconds', 30)
                    )),

                    // Multi-service: show comment/star fields when any selected service needs them
                    'needs_comment_text' => $this->serviceNeedsCommentText($service, $linkDriver),
                    'needs_star_rating' => $this->serviceNeedsStarRating($service, $linkDriver),

                    // Telegram template flags (e.g. system-managed premium folder)
                    'duration_options' => $linkDriver === 'telegram'
                        ? ($service->template()['duration_options'] ?? null)
                        : null,
                    'hide_quantity' => $linkDriver === 'telegram'
                        ? (bool) ($service->template()['hide_quantity'] ?? false)
                        : false,
                    'display_note' => $linkDriver === 'telegram'
                        ? ($service->template()['display_note'] ?? null)
                        : null,
                    'system_managed' => $linkDriver === 'telegram'
                        ? (bool) ($service->template()['system_managed'] ?? false)
                        : false,

                    // Dropdown grouping
                    'dropdown_group' => $service->template()['dropdown_group'] ?? null,
                    'dropdown_label' => $service->template()['dropdown_label'] ?? null,
                    'dropdown_priority' => $service->template()['dropdown_priority'] ?? 99,
                ];
            })
            ->values();

        return response()->json($services);
    }

    private function serviceNeedsCommentText(Service $service, string $linkDriver): bool
    {
        $tpl = $service->template();
        if (! $tpl) {
            return false;
        }
        if ($linkDriver === 'app') {
            return (bool) ($tpl['accepts_review_comments'] ?? false);
        }
        if ($linkDriver === 'youtube') {
            return YouTubeExecutionPlanResolver::stepsContainCommentCustom($tpl['steps'] ?? []);
        }

        return false;
    }

    private function serviceNeedsStarRating(Service $service, string $linkDriver): bool
    {
        if ($linkDriver !== 'app') {
            return false;
        }
        $tpl = $service->template();

        return $tpl && (bool) ($tpl['accepts_star_rating'] ?? false);
    }

    /**
     * Cancel an order fully (awaiting/pending status).
     */
    public function cancelFull(Order $order): RedirectResponse
    {
        $client = $this->client;

        try {
            $this->orderService->cancelFull($order, $client);

            return redirect()
                ->back()
                ->with('success', 'Order canceled successfully. Refund has been processed.');
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an order partially (in_progress/processing status).
     */
    public function cancelPartial(Order $order): RedirectResponse
    {
        $client = $this->client;

        try {
            $this->orderService->cancelPartial($order, $client);

            return redirect()
                ->back()
                ->with('success', 'Order partially canceled. Refund for undelivered quantity has been processed.');
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * JSON status + progress for orders on the current page (live table updates).
     */
    public function getStatuses(Request $request): JsonResponse
    {
        $orderIds = $request->input('order_ids', []);
        if (empty($orderIds) && $request->has('order_ids')) {
            $orderIds = $request->query('order_ids', []);
        }
        if (empty($orderIds) || ! is_array($orderIds)) {
            return response()->json(['statuses' => []]);
        }
        $orderIds = array_filter(array_map('intval', $orderIds));
        if (empty($orderIds)) {
            return response()->json(['statuses' => []]);
        }

        $orders = Order::query()
            ->where('client_id', $this->client->id)
            ->whereIn('id', $orderIds)
            ->select(['id', 'status', 'delivered', 'quantity', 'remains'])
            ->get();

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
