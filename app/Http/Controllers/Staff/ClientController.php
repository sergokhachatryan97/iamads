<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IndexClientRequest;
use App\Http\Requests\Staff\UpdateClientRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientServiceLimit;
use App\Models\Order;
use App\Models\ClientTransaction;
use App\Models\Service;
use App\Services\ClientServiceInterface;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(
        private ClientServiceInterface $clientService,
        private PricingService $pricingService
    ) {
    }

    /**
     * Display a listing of clients.
     */
    public function index(IndexClientRequest $request): View|Response
    {
        $filters = $request->filters();
        $currentUser = Auth::guard('staff')->user();

        $clients = $this->clientService->getPaginatedClients($filters, $currentUser);
        $staffMembers = $this->clientService->getAllStaff($currentUser);

        // Calculate totals (filtered by staff assignment for non-super_admin)
        $totalsQuery = Client::query();
        if ($currentUser && !$currentUser->hasRole('super_admin')) {
            $totalsQuery->where(function ($q) use ($currentUser) {
                $q->where('staff_id', $currentUser->id)
                  ->orWhereNull('staff_id');
            });
        }
        $totals = $totalsQuery
            ->selectRaw('COALESCE(SUM(balance), 0) as total_balance, COALESCE(SUM(spent), 0) as total_spent')
            ->first();

        $totalBalance = (float)($totals->total_balance ?? 0);
        $totalSpent = (float)($totals->total_spent ?? 0);

        // Return only table partial for AJAX requests
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('staff.clients.partials.table', compact('clients'));
        }

        return view('staff.clients.index', [
            'clients'     => $clients,
            'staffMembers' => $staffMembers,
            'filters'     => $filters,
            'totalBalance' => $totalBalance,
            'totalSpent'   => $totalSpent,
        ]);
    }

    /**
     * Delete a client.
     */
    public function destroy(Client $client): RedirectResponse
    {
        try {
            $currentUser = Auth::guard('staff')->user();
            $this->clientService->deleteClient($client, $currentUser);
            \App\Models\StaffActivityLog::log('delete', "Deleted client #{$client->id} ({$client->email})", $client);

            return redirect()->route('staff.clients.index')
                ->with('status', 'client-deleted');
        } catch (\Exception $e) {
            return redirect()->route('staff.clients.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Suspend a single client.
     */
    public function suspend(Client $client): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();

        // Permission check: non-super_admin can only suspend their own assigned clients
        if ($currentUser && !$currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            return redirect()->back()
                ->withErrors(['error' => 'You do not have permission to suspend this client.']);
        }

        // Only suspend if not already suspended
        if ($client->status !== 'suspended') {
            $client->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);
            \App\Models\StaffActivityLog::log('toggle', "Suspended client #{$client->id} ({$client->email})", $client);

            return redirect()->back()
                ->with('success', 'User suspended successfully.');
        }

        return redirect()->back()
            ->with('info', 'User is already suspended.');
    }

    /**
     * Activate a single client.
     */
    public function activate(Client $client): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();

        // Permission check: non-super_admin can only activate their own assigned clients
        if ($currentUser && !$currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            return redirect()->back()
                ->withErrors(['error' => 'You do not have permission to activate this client.']);
        }

        // Only activate if currently suspended
        if ($client->status === 'suspended') {
            $client->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);
            \App\Models\StaffActivityLog::log('toggle', "Activated client #{$client->id} ({$client->email})", $client);
        }

        return redirect()->back()
            ->with('success', 'User activated successfully.');
    }

    /**
     * Add balance manually for a client (admin).
     */
    public function addBalance(Request $request, Client $client): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();

        if ($currentUser && !$currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            return redirect()->back()
                ->withErrors(['error' => 'You do not have permission to add balance for this client.']);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['required', 'string', 'max:255'],
            'is_test_balance' => ['nullable', 'boolean'],
        ]);

        $amount = (float) $validated['amount'];
        $description = trim($validated['description']);
        $isTestBalance = (bool) ($validated['is_test_balance'] ?? false);

        DB::transaction(function () use ($client, $amount, $description, $isTestBalance) {
            $client = Client::query()->where('id', $client->id)->lockForUpdate()->firstOrFail();
            $client->balance = (float) $client->balance + $amount;
            $client->save();

            ClientTransaction::create([
                'client_id' => $client->id,
                'order_id' => null,
                'payment_id' => null,
                'amount' => $amount,
                'type' => ClientTransaction::TYPE_MANUAL_CREDIT,
                'description' => $description,
                'is_test_balance' => $isTestBalance,
            ]);
        });

        $testLabel = $isTestBalance ? ' [TEST]' : '';
        \App\Models\StaffActivityLog::log('balance', "Added \${$amount}{$testLabel} balance to client #{$client->id} ({$client->email})", $client, [
            'amount' => $amount,
            'description' => $description,
            'is_test_balance' => $isTestBalance,
        ]);

        return redirect()->route('staff.clients.edit', $client)
            ->with('success', 'Balance added successfully. New balance: $' . number_format((float) $client->fresh()->balance, 2));
    }

    /**
     * Deduct balance manually for a client (staff).
     */
    public function deductBalance(Request $request, Client $client): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();

        if ($currentUser && ! $currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            return redirect()->back()
                ->withErrors(['error' => 'You do not have permission to adjust balance for this client.']);
        }

        $validated = $request->validate([
            'deduct_amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'deduct_description' => ['required', 'string', 'max:255'],
            'deduct_is_test_balance' => ['nullable', 'boolean'],
        ]);

        $amount = (float) $validated['deduct_amount'];
        $description = trim($validated['deduct_description']);
        $isTestBalance = (bool) ($validated['deduct_is_test_balance'] ?? false);

        try {
            DB::transaction(function () use ($client, $amount, $description, $isTestBalance) {
                $client = Client::query()->where('id', $client->id)->lockForUpdate()->firstOrFail();
                $balance = (float) $client->balance;

                if ($balance + 1e-6 < $amount) {
                    throw ValidationException::withMessages([
                        'deduct_amount' => __('Insufficient balance. Current balance: :balance', [
                            'balance' => '$'.number_format($balance, 2),
                        ]),
                    ]);
                }

                $client->balance = $balance - $amount;
                $client->save();

                ClientTransaction::create([
                    'client_id' => $client->id,
                    'order_id' => null,
                    'payment_id' => null,
                    'amount' => -$amount,
                    'type' => ClientTransaction::TYPE_MANUAL_DEBIT,
                    'description' => $description,
                    'is_test_balance' => $isTestBalance,
                ]);
            });
        } catch (ValidationException $e) {
            return redirect()->route('staff.clients.edit', $client)
                ->withErrors($e->errors())
                ->withInput($request->only(['deduct_amount', 'deduct_description', 'deduct_is_test_balance']));
        }

        $testLabel = $isTestBalance ? ' [TEST]' : '';
        \App\Models\StaffActivityLog::log('balance', "Deducted \${$amount}{$testLabel} from client #{$client->id} ({$client->email})", $client, [
            'amount' => $amount,
            'description' => $description,
            'is_test_balance' => $isTestBalance,
        ]);

        return redirect()->route('staff.clients.edit', $client)
            ->with('success', 'Balance deducted successfully. New balance: $'.number_format((float) $client->fresh()->balance, 2));
    }

    /**
     * Bulk suspend clients.
     */
    public function bulkSuspend(Request $request): RedirectResponse
    {
        $request->validate([
            'client_ids' => ['required', 'array'],
            'client_ids.*' => ['required', 'integer', 'exists:clients,id'],
        ]);

        $currentUser = Auth::guard('staff')->user();
        $clientIds = $request->input('client_ids');

        // Permission check: non-super_admin can only suspend their own assigned clients
        if ($currentUser && !$currentUser->hasRole('super_admin')) {
            // Verify all clients belong to this staff member
            $unauthorizedClients = Client::whereIn('id', $clientIds)
                ->where('staff_id', '!=', $currentUser->id)
                ->exists();

            if ($unauthorizedClients) {
                return redirect()->back()
                    ->withErrors(['error' => 'You do not have permission to suspend one or more selected clients.']);
            }
        }

        // Only suspend clients that are currently active
        Client::whereIn('id', $clientIds)
            ->where('status', 'active')
            ->update([
                'status' => 'suspended',
                'suspended_at' => now(),
            ]);

        return redirect()->back()
            ->with('success', 'Selected users suspended successfully.');
    }

    /**
     * Bulk activate clients.
     */
    public function bulkActivate(Request $request): RedirectResponse
    {
        $request->validate([
            'client_ids' => ['required', 'array'],
            'client_ids.*' => ['required', 'integer', 'exists:clients,id'],
        ]);

        $currentUser = Auth::guard('staff')->user();
        $clientIds = $request->input('client_ids');

        // Permission check: non-super_admin can only activate their own assigned clients
        if ($currentUser && !$currentUser->hasRole('super_admin')) {
            // Verify all clients belong to this staff member
            $unauthorizedClients = Client::whereIn('id', $clientIds)
                ->where('staff_id', '!=', $currentUser->id)
                ->exists();

            if ($unauthorizedClients) {
                return redirect()->back()
                    ->withErrors(['error' => 'You do not have permission to activate one or more selected clients.']);
            }
        }

        // Only activate clients that are currently suspended
        Client::whereIn('id', $clientIds)
            ->where('status', 'suspended')
            ->update([
                'status' => 'active',
                'suspended_at' => null,
            ]);

        return redirect()->back()
            ->with('success', 'Selected users activated successfully.');
    }

    /**
     * Assign staff member to a client.
     */
    public function assignStaff(Request $request, Client $client): RedirectResponse
    {
        $request->validate([
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $currentUser = Auth::guard('staff')->user();

        // Only super_admin can assign staff
        if (!$currentUser || !$currentUser->hasRole('super_admin')) {
            return redirect()->route('staff.clients.index')
                ->withErrors(['error' => 'You do not have permission to assign staff members.']);
        }

        $client->update([
            'staff_id' => $request->input('staff_id') ?: null,
        ]);

        // Redirect back to edit page if coming from edit page, otherwise index
        $referer = $request->header('referer');
        if ($referer && str_contains($referer, '/staff/clients/' . $client->id . '/edit')) {
            return redirect()->route('staff.clients.edit', $client)
                ->with('success', 'Staff member assigned successfully.');
        }

        return redirect()->route('staff.clients.index')
            ->with('status', 'staff-assigned');
    }

    /**
     * Show the form for editing client discount and rates.
     */
    public function edit(Client $client): View
    {
        $currentUser = Auth::guard('staff')->user();

        // Permission check: non-super_admin can only edit their own assigned clients
        if ($currentUser && !$currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            abort(403, 'You do not have permission to edit this client.');
        }

        $clientRates = is_array($client->rates) ? $client->rates : [];
        $disabledServiceIds = array_keys($clientRates);

        $disabledRatesLookup = array_fill_keys(array_map('strval', $disabledServiceIds), true);

        $categories = Category::query()
            ->where('status', true)
            ->whereHas('services', function ($q) {
                $q->where('is_active', true)->whereNull('deleted_at');
            })
            ->with(['services' => function ($q) {
                $q->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        $recentSignIns = $client->loginLogs()
            ->orderBy('signed_in_at', 'desc')
            ->limit(10)
            ->get();

        $baseCategories = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'services' => $category->services->map(function ($service) {
                    return [
                        'id' => (string) $service->id,
                        'name' => $service->name,
                        'price' => (float) ($service->rate_per_1000 ?? 0),
                    ];
                })->values()->all(),
            ];
        })->values();

        // categoriesData: same base, but exclude disabled (already has custom rate)
        $categoriesData = $baseCategories->map(function ($cat) use ($disabledRatesLookup) {
            $cat['services'] = array_values(array_filter($cat['services'], function ($s) use ($disabledRatesLookup) {
                return !isset($disabledRatesLookup[(string) $s['id']]);
            }));
            return $cat;
        })->filter(fn ($cat) => count($cat['services']) > 0)
            ->values()
            ->all();

        // selectOptions: [CategoryName => [{value,label,price}, ...]]
        $selectOptions = $baseCategories->mapWithKeys(function ($cat) {
            $services = array_map(function ($s) {
                return [
                    'value' => (string) $s['id'],
                    'label' => $s['name'],
                    'price' => (float) $s['price'],
                ];
            }, $cat['services']);

            return [$cat['name'] => $services];
        })->filter(fn ($services) => count($services) > 0)
            ->all();

        // Get staff members for assignment (only if super_admin) — keep your existing logic
        $staffMembers = $this->clientService->getAllStaff($currentUser);

        // All active services list for table (DB filtered; also eager-load category)
        $services = Service::query()
            ->select(['id', 'name', 'rate_per_1000', 'category_id', 'is_active', 'deleted_at'])
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->with('category:id,name')
            ->orderBy('category_id')
            ->orderBy('name')
            ->get();

        $priceCache = [];

        $allServices = $services->map(function ($service) use ($client, $clientRates, &$priceCache) {
            $serviceIdStr = (string) $service->id;
            $defaultPrice = (float) ($service->rate_per_1000 ?? 0);

            if (!array_key_exists($serviceIdStr, $priceCache)) {
                $priceCache[$serviceIdStr] = $this->pricingService->priceForClient($service, $client);
            }
            $clientPrice = (float) $priceCache[$serviceIdStr];

            // Custom rate extraction (accept both int/string keys)
            $rateData = $clientRates[$serviceIdStr] ?? ($clientRates[$service->id] ?? null);

            $hasCustomRate = is_array($rateData)
                && isset($rateData['type'], $rateData['value']);

            return [
                'service' => $service,
                'default_price' => $defaultPrice,
                'client_price' => $clientPrice,
                'has_custom_rate' => $hasCustomRate,
                'custom_rate_type' => $hasCustomRate ? $rateData['type'] : null,
                'custom_rate_value' => $hasCustomRate ? (float) $rateData['value'] : null,
            ];
        });

        // Existing service limits for this client
        $serviceLimits = $client->serviceLimits()
            ->with('service.category')
            ->get();

        $serviceLimitsCategoriesData = $baseCategories->map(function ($cat) {
            return [
                'id' => $cat['id'],
                'name' => $cat['name'],
                'services' => array_values(array_map(function ($s) {
                    return [
                        'id' => (string) $s['id'],
                        'name' => $s['name'],
                    ];
                }, $cat['services'])),
            ];
        })->filter(fn ($cat) => count($cat['services']) > 0)
            ->values()
            ->all();

        // Disabled service IDs for limits dropdown (services that already have limits)
        $disabledServiceLimitIds = $serviceLimits
            ->pluck('service_id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $clientTransactions = $client->transactions()
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'transactions_page')
            ->appends(request()->except('transactions_page'));

        $payments = $client->payments()
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'payments_page')
            ->appends(request()->except('payments_page'));

        // Client orders with filters
        $ordersQuery = Order::query()
            ->where('orders.client_id', $client->id)
            ->join('services', 'orders.service_id', '=', 'services.id')
            ->join('categories', 'services.category_id', '=', 'categories.id')
            ->select([
                'orders.*',
                'services.name as service_name',
                'categories.name as category_name',
            ]);

        // Apply filters
        if (request('orders_status')) {
            $ordersQuery->where('orders.status', request('orders_status'));
        }
        if (request('orders_category_id')) {
            $ordersQuery->where('categories.id', request('orders_category_id'));
        }
        if (request('orders_service_id')) {
            $ordersQuery->where('orders.service_id', request('orders_service_id'));
        }
        if (request('orders_date_from')) {
            $ordersQuery->where('orders.created_at', '>=', request('orders_date_from') . ' 00:00:00');
        }
        if (request('orders_date_to')) {
            $ordersQuery->where('orders.created_at', '<=', request('orders_date_to') . ' 23:59:59');
        }

        // Sorting
        $ordersSortBy = request('orders_sort', 'created_at');
        $ordersSortDir = request('orders_dir', 'desc');
        $allowedSorts = ['id', 'created_at', 'charge', 'quantity', 'status', 'category_name', 'service_name'];
        if (!in_array($ordersSortBy, $allowedSorts)) {
            $ordersSortBy = 'created_at';
        }
        $sortColumn = match ($ordersSortBy) {
            'category_name' => 'categories.name',
            'service_name' => 'services.name',
            default => 'orders.' . $ordersSortBy,
        };
        $ordersQuery->orderBy($sortColumn, $ordersSortDir === 'asc' ? 'asc' : 'desc');

        $clientOrders = $ordersQuery
            ->paginate(15, ['*'], 'orders_page')
            ->appends(request()->except('orders_page'));

        // All categories for platform filter dropdown
        $orderCategories = Category::query()
            ->where('status', true)
            ->orderBy('name')
            ->pluck('name', 'id');

        // Services grouped by dropdown_group (from template) for the service filter
        $orderServicesGrouped = Service::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('category', fn ($q) => $q->where('status', true))
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->map(function ($service) {
                $tpl = $service->template();
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'category_id' => $service->category_id,
                    'group' => $tpl['dropdown_group'] ?? $service->category->name ?? 'Other',
                    'group_label' => $tpl['dropdown_label'] ?? $tpl['dropdown_group'] ?? $service->category->name ?? 'Other',
                    'group_priority' => $tpl['dropdown_priority'] ?? 99,
                ];
            })
            ->groupBy('group')
            ->sortBy(fn ($group) => $group->first()['group_priority'])
            ->map(fn ($services, $group) => [
                'label' => $services->first()['group_label'],
                'services' => $services->values()->all(),
            ])
            ->values()
            ->all();

        // All statuses for status filter
        $orderStatuses = [
            Order::STATUS_VALIDATING, Order::STATUS_INVALID_LINK, Order::STATUS_AWAITING,
            Order::STATUS_IN_PROGRESS, Order::STATUS_PROCESSING, Order::STATUS_PARTIAL,
            Order::STATUS_COMPLETED, Order::STATUS_CANCELED, Order::STATUS_FAIL,
        ];

        return view('staff.clients.edit', [
            'client' => $client,
            'categories' => $categories,
            'recentSignIns' => $recentSignIns,
            'categoriesData' => $categoriesData,
            'selectOptions' => $selectOptions,
            'disabledServiceIds' => $disabledServiceIds,
            'staffMembers' => $staffMembers,
            'allServices' => $allServices,
            'serviceLimits' => $serviceLimits,
            'serviceLimitsCategoriesData' => $serviceLimitsCategoriesData,
            'disabledServiceLimitIds' => $disabledServiceLimitIds,
            'clientTransactions' => $clientTransactions,
            'payments' => $payments,
            'clientOrders' => $clientOrders,
            'orderCategories' => $orderCategories,
            'orderServicesGrouped' => $orderServicesGrouped,
            'orderStatuses' => $orderStatuses,
        ]);
    }


    /**
     * Update client discount and rates.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();

        if ($currentUser && !$currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            abort(403, 'You do not have permission to update this client.');
        }

        $ratesInput = $request->input('rates', []);
        $rates = [];

        if (is_array($ratesInput)) {
            foreach ($ratesInput as $serviceId => $rateData) {
                if (!is_array($rateData)) continue;

                // Skip if marked for removal
                if (!empty($rateData['remove']) && $rateData['remove'] == '1') {
                    continue;
                }

                if (!empty($rateData['enabled'])
                    && isset($rateData['type'], $rateData['value'])
                    && $rateData['value'] !== null
                    && $rateData['value'] !== ''
                ) {
                    $rates[(string)$serviceId] = [
                        'type'  => $rateData['type'],
                        'value' => (float) $rateData['value'],
                    ];
                }
            }
        }

        // -------- Discount (normalize) --------
        $discountRaw = $request->input('discount');
        $discount = (is_numeric($discountRaw) && (float)$discountRaw > 0) ? (float)$discountRaw : 0;

        // -------- Social media (normalize to platform => username) --------
        $socialMediaInput = $request->input('social_media', []);
        $socialMedia = [];

        if (is_array($socialMediaInput)) {
            foreach ($socialMediaInput as $item) {
                if (!is_array($item)) continue;

                $platform = $item['platform'] ?? null;
                $username = $item['username'] ?? null;

                if ($platform && $username !== null && trim((string)$username) !== '') {
                    $socialMedia[(string)$platform] = trim((string)$username);
                }
            }
        }

        // -------- Service limits (batch operations) --------
        $serviceLimitsInput = $request->input('service_limits', []);

        $toDeleteServiceIds = [];
        $toUpsert = [];

        if (is_array($serviceLimitsInput) && !empty($serviceLimitsInput)) {
            $serviceIds = array_keys($serviceLimitsInput);

            // Validate services exist in ONE query
            $existingServiceIds = Service::query()
                ->whereIn('id', $serviceIds)
                ->pluck('id')
                ->map(fn($id) => (string)$id)
                ->all();

            $existingLookup = array_fill_keys($existingServiceIds, true);

            foreach ($serviceLimitsInput as $serviceId => $limitData) {
                $serviceIdStr = (string)$serviceId;

                // skip unknown service ids
                if (!isset($existingLookup[$serviceIdStr])) {
                    continue;
                }

                if (!is_array($limitData)) {
                    continue;
                }

                if (!empty($limitData['remove']) && $limitData['remove'] == '1') {
                    $toDeleteServiceIds[] = $serviceIdStr;
                    continue;
                }

                $minQty = (isset($limitData['min_quantity']) && $limitData['min_quantity'] !== '')
                    ? (int)$limitData['min_quantity']
                    : null;

                $maxQty = (isset($limitData['max_quantity']) && $limitData['max_quantity'] !== '')
                    ? (int)$limitData['max_quantity']
                    : null;

                $increment = (isset($limitData['increment']) && $limitData['increment'] !== '')
                    ? (int)$limitData['increment']
                    : null;

                $overflowPercent = (isset($limitData['overflow_percent']) && $limitData['overflow_percent'] !== '')
                    ? (float)$limitData['overflow_percent']
                    : null;

                // Validate max >= min when both present
                if ($minQty !== null && $maxQty !== null && $maxQty < $minQty) {
                    return redirect()
                        ->route('staff.clients.edit', $client)
                        ->withErrors([
                            'service_limits' => "Max quantity must be greater than or equal to min quantity for service ID {$serviceIdStr}."
                        ])
                        ->withInput();
                }

                $hasAnyValue = ($minQty !== null || $maxQty !== null || $increment !== null || $overflowPercent !== null);

                if (!$hasAnyValue) {
                    $toDeleteServiceIds[] = $serviceIdStr;
                    continue;
                }

                $toUpsert[] = [
                    'client_id'        => $client->id,
                    'service_id'       => $serviceIdStr,
                    'min_quantity'     => $minQty,
                    'max_quantity'     => $maxQty,
                    'increment'        => $increment,
                    'overflow_percent' => $overflowPercent,
                ];
            }
        }

        // -------- Prepare update data --------
        $updateData = [
            'discount'     => $discount,
            'rates'        => !empty($rates) ? $rates : null,
            'social_media' => !empty($socialMedia) ? $socialMedia : null,
        ];

        // Only super_admin can update staff_id
        if ($currentUser && $currentUser->hasRole('super_admin') && $request->has('staff_id')) {
            $updateData['staff_id'] = $request->input('staff_id') ?: null;
        }

        try {
            DB::transaction(function () use ($client, $updateData, $toDeleteServiceIds, $toUpsert) {
                $client->update($updateData);

                // Batch delete limits
                if (!empty($toDeleteServiceIds)) {
                    ClientServiceLimit::query()
                        ->where('client_id', $client->id)
                        ->whereIn('service_id', array_values(array_unique($toDeleteServiceIds)))
                        ->delete();
                }

                // Batch upsert limits (fast, no per-row query)
                if (!empty($toUpsert)) {
                    // Uses unique(client_id, service_id)
                    ClientServiceLimit::query()->upsert(
                        $toUpsert,
                        ['client_id', 'service_id'],
                        ['min_quantity', 'max_quantity', 'increment', 'overflow_percent']
                    );
                }
            });

            \App\Models\StaffActivityLog::log('update', "Updated client #{$client->id} ({$client->email})", $client, $updateData);

            return redirect()
                ->route('staff.clients.edit', $client)
                ->with('success', __('Client updated successfully.'));
        } catch (\Throwable $e) {
            return redirect()
                ->route('staff.clients.edit', $client)
                ->with('error', __('Failed to update client. Please try again.'));
        }
    }
}
