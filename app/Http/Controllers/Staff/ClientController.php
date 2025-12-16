<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IndexClientRequest;
use App\Http\Requests\Staff\UpdateClientRequest;
use App\Models\Client;
use App\Services\ClientServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function __construct(
        private ClientServiceInterface $clientService
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

        // Return only table partial for AJAX requests
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('staff.clients.partials.table', compact('clients'));
        }

        return view('staff.clients.index', [
            'clients'     => $clients,
            'staffMembers' => $staffMembers,
            'filters'     => $filters,
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
     * Assign staff member to a client.
     */
    public function assignStaff(Request $request, Client $client): RedirectResponse
    {
        $request->validate([
            'staff_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $currentUser = Auth::guard('staff')->user();

        // Only super_admin can assign staff
        if (!$currentUser || !$currentUser->hasRole('super_admin')) {
            return redirect()->route('staff.clients.index')
                ->withErrors(['error' => 'You do not have permission to assign staff members.']);
        }

        $client->update([
            'staff_id' => $request->input('staff_id'),
        ]);

        return redirect()->route('staff.clients.index')
            ->with('status', 'staff-assigned');
    }

    /**
     * Show the form for editing client discount and rates.
     */
    public function edit(Client $client): View
    {
        return view('staff.clients.edit', [
            'client' => $client,
        ]);
    }

    /**
     * Update client discount and rates.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        // Build rates array from key-value pairs
        $rates = [];
        $keys = $request->input('rates_key', []);
        $values = $request->input('rates_value', []);

        foreach ($keys as $index => $key) {
            if (!empty($key) && isset($values[$index]) && $values[$index] !== null && $values[$index] !== '') {
                $rates[$key] = (float) $values[$index];
            }
        }

        $client->update([
            'discount' => $request->input('discount'),
            'rates' => $rates,
        ]);

        return redirect()->route('staff.clients.index')
            ->with('status', 'client-updated');
    }
}

