<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoginLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientLoginLogController extends Controller
{
    /**
     * Display sign-in history for a client.
     */
    public function index(Request $request, Client $client): View
    {
        $query = $client->loginLogs()->orderBy('signed_in_at', 'desc');

        // Filter by IP
        if ($request->filled('ip')) {
            $query->where('ip', 'like', '%' . $request->input('ip') . '%');
        }

        // Filter by device_type
        if ($request->filled('device_type')) {
            $query->where('device_type', $request->input('device_type'));
        }

        // Filter by OS
        if ($request->filled('os')) {
            $query->where('os', 'like', '%' . $request->input('os') . '%');
        }

        // Filter by browser
        if ($request->filled('browser')) {
            $query->where('browser', 'like', '%' . $request->input('browser') . '%');
        }

        // Filter by country
        if ($request->filled('country')) {
            $query->where('country', $request->input('country'));
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->where('city', 'like', '%' . $request->input('city') . '%');
        }

        $logs = $query->paginate(25)->withQueryString();

        // Get unique values for filter dropdowns
        $deviceTypes = ClientLoginLog::where('client_id', $client->id)
            ->whereNotNull('device_type')
            ->distinct()
            ->pluck('device_type')
            ->sort()
            ->values();

        $oses = ClientLoginLog::where('client_id', $client->id)
            ->whereNotNull('os')
            ->distinct()
            ->pluck('os')
            ->sort()
            ->values();

        $browsers = ClientLoginLog::where('client_id', $client->id)
            ->whereNotNull('browser')
            ->distinct()
            ->pluck('browser')
            ->sort()
            ->values();

        $countries = ClientLoginLog::where('client_id', $client->id)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values();

        return view('staff.clients.sign-ins', [
            'client' => $client,
            'logs' => $logs,
            'deviceTypes' => $deviceTypes,
            'oses' => $oses,
            'browsers' => $browsers,
            'countries' => $countries,
            'filters' => $request->only(['ip', 'device_type', 'os', 'browser', 'country', 'city']),
        ]);
    }

    /**
     * Find clients who have logged in from the same IPs as the given client.
     */
    public function matchingIps(Client $client): View
    {
        // Get all distinct IPs this client has logged in from
        $clientIps = ClientLoginLog::where('client_id', $client->id)
            ->distinct()
            ->pluck('ip')
            ->toArray();

        if (empty($clientIps)) {
            return view('staff.clients.matching-ips', [
                'client' => $client,
                'matches' => collect([]),
                'clientIps' => [],
            ]);
        }

        // Find other clients who have logged in from any of these IPs
        $matchingLogs = ClientLoginLog::whereIn('ip', $clientIps)
            ->where('client_id', '!=', $client->id)
            ->with('client:id,name,email')
            ->get();

        // Group by client and collect matched IPs and last seen
        $matches = $matchingLogs->groupBy('client_id')->map(function ($logs, $clientId) {
            $firstLog = $logs->first();
            $matchedIps = $logs->pluck('ip')->unique()->values();
            $lastSeen = $logs->max('signed_in_at');

            return [
                'client' => $firstLog->client,
                'matched_ips' => $matchedIps,
                'last_seen' => $lastSeen,
            ];
        })->values();

        return view('staff.clients.matching-ips', [
            'client' => $client,
            'matches' => $matches,
            'clientIps' => $clientIps,
        ]);
    }
}