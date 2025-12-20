<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IndexClientRequest;
use App\Http\Requests\Staff\UpdateClientRequest;
use App\Models\Client;
use App\Services\ClientServiceInterface;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
        // Get only active categories with active services (not deleted)
        $categories = \App\Models\Category::where('status', true)
            ->with(['services' => function ($query) {
                $query->where('is_active', true)
                    ->whereNull('deleted_at') // Explicitly exclude soft-deleted services
                    ->orderBy('name');
            }])
            ->orderBy('name')
            ->get()
            ->filter(function($category) {
                // Only include categories that have at least one active service
                return $category->services->count() > 0;
            });

        // Get recent sign-in logs (last 10)
        $recentSignIns = $client->loginLogs()->orderBy('signed_in_at', 'desc')->take(10)->get();

        // Get services that already have custom rates
        $clientRates = is_array($client->rates) ? $client->rates : [];
        $disabledServiceIds = array_keys($clientRates);

        // Prepare categories data for JavaScript (only active services, excluding those already in use)
        $categoriesData = $categories->map(function($category) use ($disabledServiceIds) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'services' => $category->services->filter(function($service) use ($disabledServiceIds) {
                    // Only active, not deleted, and not already in use
                    return $service->is_active
                        && !$service->trashed()
                        && !in_array((string)$service->id, array_map('strval', $disabledServiceIds));
                })->map(function($service) {
                    return [
                        'id' => (string)$service->id,
                        'name' => $service->name,
                        'price' => (float)($service->rate_per_1000 ?? 0)
                    ];
                })->values()->toArray()
            ];
        })->filter(function($category) {
            // Only include categories that have services after filtering
            return count($category['services']) > 0;
        })->values()->toArray();

        $selectOptions = $categories->mapWithKeys(function($category) {
            $services = $category->services->filter(function($service) {
                // Only active and not deleted
                return $service->is_active && !$service->trashed();
            })->map(function($service) {
                return [
                    'value' => (string)$service->id,
                    'label' => $service->name,
                    'price' => (float)($service->rate_per_1000 ?? 0)
                ];
            })->toArray();
            return [$category->name => $services];
        })->filter(function($services) {
            // Only include categories that have services
            return count($services) > 0;
        })->toArray();

        // Get staff members for assignment (only if super_admin)
        $staffMembers = $this->clientService->getAllStaff($currentUser);

        // Get all active services with calculated prices for this client
        $allServices = \App\Models\Service::where('is_active', true)
            ->whereNull('deleted_at')
            ->with('category:id,name')
            ->orderBy('category_id')
            ->orderBy('name')
            ->get()
            ->map(function($service) use ($client) {
                $defaultPrice = (float)($service->rate_per_1000 ?? 0);
                $clientPrice = $this->pricingService->priceForClient($service, $client);

                // Check if client has custom rate for this service
                // Handle both string and integer keys (JSON stores as strings)
                $clientRates = is_array($client->rates) ? $client->rates : [];
                $serviceIdStr = (string)$service->id;

                // Check for custom rate with both integer and string keys
                $rateData = null;
                if (isset($clientRates[$service->id])) {
                    $rateData = $clientRates[$service->id];
                } elseif (isset($clientRates[$serviceIdStr])) {
                    $rateData = $clientRates[$serviceIdStr];
                }

                $hasCustomRate = false;
                $customRateType = null;
                $customRateValue = null;

                // Only mark as custom rate if we have valid type and value
                if ($rateData && is_array($rateData) && isset($rateData['type']) && isset($rateData['value'])) {
                    $hasCustomRate = true;
                    $customRateType = $rateData['type'];
                    $customRateValue = (float)$rateData['value'];
                }

                return [
                    'service' => $service,
                    'default_price' => $defaultPrice,
                    'client_price' => $clientPrice,
                    'has_custom_rate' => $hasCustomRate,
                    'custom_rate_type' => $customRateType,
                    'custom_rate_value' => $customRateValue,
                ];
            });

        return view('staff.clients.edit', [
            'client' => $client,
            'categories' => $categories,
            'recentSignIns' => $recentSignIns,
            'categoriesData' => $categoriesData,
            'selectOptions' => $selectOptions,
            'disabledServiceIds' => $disabledServiceIds,
            'staffMembers' => $staffMembers,
            'allServices' => $allServices,
        ]);
    }

    /**
     * Update client discount and rates.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $currentUser = Auth::guard('staff')->user();
        
        // Permission check: non-super_admin can only update their own assigned clients
        if ($currentUser && !$currentUser->hasRole('super_admin') && $client->staff_id !== $currentUser->id) {
            abort(403, 'You do not have permission to update this client.');
        }
        // Build rates array from service IDs with type and value
        $rates = [];
        $ratesInput = $request->input('rates', []);

        foreach ($ratesInput as $serviceId => $rateData) {
            // Skip if marked for removal
            if (!empty($rateData['remove']) && $rateData['remove'] == '1') {
                continue;
            }

            // Only include rates that are enabled and have valid type and value
            if (!empty($rateData['enabled']) &&
                isset($rateData['type']) &&
                isset($rateData['value']) &&
                $rateData['value'] !== null &&
                $rateData['value'] !== '') {
                $rates[$serviceId] = [
                    'type' => $rateData['type'],
                    'value' => (float) $rateData['value'],
                ];
            }
        }

        // Set discount: if empty or 0, save as 0
        $discount = $request->input('discount');
        if ($discount === null || $discount === '' || $discount == 0) {
            $discount = 0;
        }

        // Prepare update data
        $updateData = [
            'discount' => $discount,
            'rates' => !empty($rates) ? $rates : null,
        ];

        // Only super_admin can update staff_id
        if ($currentUser && $currentUser->hasRole('super_admin') && $request->has('staff_id')) {
            $updateData['staff_id'] = $request->input('staff_id') ?: null;
        }

        try {
            $client->update($updateData);

            return redirect()->route('staff.clients.edit', $client)
                ->with('success', __('Client updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->route('staff.clients.edit', $client)
                ->with('error', __('Failed to update client. Please try again.'));
        }
    }
}
