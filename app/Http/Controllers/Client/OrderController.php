<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\MultiStoreOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Client;
use App\Models\Order;
use App\Models\Service;
use App\Services\CategoryServiceInterface;
use App\Services\OrderServiceInterface;
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
        $query = Order::where('client_id', $this->client->id)->with(['service']);

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

        $orders = $query->paginate(20)->withQueryString();

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

        return view('client.orders.index', [
            'orders' => $orders,
            'statuses' => $statuses,
            'currentStatus' => $request->get('status', 'all'),
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    /**
     * Show the form for creating a new order.
     */
    public function create(Request $request): View
    {
        $categories = $this->categoryService->getAllCategories(true);

        // Get pre-selected category and service from query parameters
        $preselectedCategoryId = $request->get('category_id');
        $preselectedServiceId = $request->get('service_id');

        return view('client.orders.create', [
            'categories' => $categories,
            'preselectedCategoryId' => $preselectedCategoryId,
            'preselectedServiceId' => $preselectedServiceId,
        ]);
    }

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request): RedirectResponse
    {

        try {
            $orders = $this->orderService->create($this->client, $request->payload());

            $count = $orders->count();

            return redirect()
                ->route('client.orders.index')
                ->with('success', $count === 1 ? 'Order created successfully.' : "{$count} orders created successfully.");
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

            $count = $orders->count();

            return redirect()
                ->route('client.orders.index')
                ->with('success', $count === 1 ? 'Order created successfully.' : "{$count} orders created successfully.");
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
     * Get services by category (JSON endpoint for dynamic dropdown).
     */
    public function servicesByCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $client = $this->client;

        $categoryId = (int) $validated['category_id'];

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

        $services = Service::query()
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->orderBy('name')
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
            ])
            ->map(function (Service $service) use ($clientRates, $serviceLimits, $client, $pricingService, $clientDiscount) {

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
                ];
            })
            ->values(); // ensures clean 0..N array in JSON

        return response()->json($services);
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
                ->route('client.orders.index')
                ->with('success', 'Order canceled successfully. Refund has been processed.');
        } catch (ValidationException $e) {
            return redirect()
                ->route('client.orders.index')
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()
                ->route('client.orders.index')
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
                ->route('client.orders.index')
                ->with('success', 'Order partially canceled. Refund for undelivered quantity has been processed.');
        } catch (ValidationException $e) {
            return redirect()
                ->route('client.orders.index')
                ->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()
                ->route('client.orders.index')
                ->with('error', $e->getMessage());
        }
    }
}

