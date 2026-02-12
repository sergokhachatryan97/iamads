<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IndexClientRequest;
use App\Http\Requests\Staff\UpdateClientRequest;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientServiceLimit;
use App\Models\Service;
use App\Services\ClientServiceInterface;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $totalsQuery->where('staff_id', $currentUser->id);
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
        }

        return redirect()->back()
            ->with('success', 'User activated successfully.');
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

                // Validate max >= min when both present
                if ($minQty !== null && $maxQty !== null && $maxQty < $minQty) {
                    return redirect()
                        ->route('staff.clients.edit', $client)
                        ->withErrors([
                            'service_limits' => "Max quantity must be greater than or equal to min quantity for service ID {$serviceIdStr}."
                        ])
                        ->withInput();
                }

                $hasAnyValue = ($minQty !== null || $maxQty !== null || $increment !== null);

                if (!$hasAnyValue) {
                    $toDeleteServiceIds[] = $serviceIdStr;
                    continue;
                }

                $toUpsert[] = [
                    'client_id'     => $client->id,
                    'service_id'    => $serviceIdStr,
                    'min_quantity'  => $minQty,
                    'max_quantity'  => $maxQty,
                    'increment'     => $increment,
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
                        ['min_quantity', 'max_quantity', 'increment']
                    );
                }
            });

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
