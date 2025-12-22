<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientLoginLog;
use App\Repositories\ClientLoginLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Jenssegers\Agent\Agent;

class ClientLoginLogService implements ClientLoginLogServiceInterface
{
    public function __construct(
        private ClientLoginLogRepositoryInterface $clientLoginLogRepository
    ) {
    }

    /**
     * Get paginated login logs for a client with filters.
     *
     * @param int $clientId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedLogsForClient(int $clientId, array $filters = []): LengthAwarePaginator
    {
        return $this->clientLoginLogRepository->getPaginatedForClient($clientId, $filters);
    }

    /**
     * Get unique device types for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueDeviceTypes(int $clientId): Collection
    {
        return $this->clientLoginLogRepository->getUniqueDeviceTypes($clientId);
    }

    /**
     * Get unique operating systems for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueOperatingSystems(int $clientId): Collection
    {
        return $this->clientLoginLogRepository->getUniqueOperatingSystems($clientId);
    }

    /**
     * Get unique browsers for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueBrowsers(int $clientId): Collection
    {
        return $this->clientLoginLogRepository->getUniqueBrowsers($clientId);
    }

    /**
     * Get unique countries for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueCountries(int $clientId): Collection
    {
        return $this->clientLoginLogRepository->getUniqueCountries($clientId);
    }

    /**
     * Get distinct IPs for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getDistinctIps(int $clientId): Collection
    {
        return $this->clientLoginLogRepository->getDistinctIps($clientId);
    }

    /**
     * Find clients who have logged in from the same IPs as the given client.
     *
     * @param int $clientId
     * @return array Returns array with 'clientIps' and 'matches'
     */
    public function findMatchingIps(int $clientId): array
    {
        $clientIps = $this->clientLoginLogRepository->getDistinctIps($clientId)->toArray();

        if (empty($clientIps)) {
            return [
                'clientIps' => [],
                'matches' => collect([]),
            ];
        }

        $matchingLogs = $this->clientLoginLogRepository->findByIps($clientIps, $clientId);

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

        return [
            'clientIps' => $clientIps,
            'matches' => $matches,
        ];
    }

    /**
     * Create a new login log.
     *
     * @param array $data
     * @return ClientLoginLog
     */
    public function createLog(array $data): ClientLoginLog
    {
        return $this->clientLoginLogRepository->create($data);
    }

    /**
     * Create a login log from request data (with device and location detection).
     *
     * @param Client $client
     * @param Request $request
     * @return ClientLoginLog
     */
    public function createLoginLogFromRequest(Client $client, Request $request): ClientLoginLog
    {
        $agent = new Agent();
        $userAgent = $request->userAgent();

        // Parse user agent if available
        if ($userAgent) {
            $agent->setUserAgent($userAgent);
        }

        // Determine device type
        $deviceType = 'unknown';
        if ($agent->isRobot()) {
            $deviceType = 'bot';
        } elseif ($agent->isMobile()) {
            $deviceType = 'mobile';
        } elseif ($agent->isTablet()) {
            $deviceType = 'tablet';
        } elseif ($agent->isDesktop()) {
            $deviceType = 'desktop';
        }

        // Get device name
        $deviceName = $agent->device();

        // Get IP address
        $ip = $request->ip();

        // Get location data from IP
        $locationData = $this->getLocationFromIp($ip);

        return $this->clientLoginLogRepository->create([
            'client_id' => $client->id,
            'signed_in_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device_type' => $deviceType,
            'os' => $agent->platform(),
            'browser' => $agent->browser(),
            'device_name' => $deviceName,
            'country' => $locationData['country'] ?? null,
            'city' => $locationData['city'] ?? null,
            'lat' => $locationData['lat'] ?? null,
            'lng' => $locationData['lng'] ?? null,
        ]);
    }

    /**
     * Get location data from IP address using free GeoIP service.
     *
     * @param string $ip
     * @return array
     */
    private function getLocationFromIp(string $ip): array
    {
        // Skip local/private IPs
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [];
        }

        try {
            // Use ip-api.com free service (no API key required, 45 requests/minute limit)
            // Using Laravel's HTTP client with timeout to avoid blocking login
            $response = Http::timeout(2)
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,countryCode,city,lat,lon'
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['status']) && $data['status'] === 'success') {
                    return [
                        'country' => $data['countryCode'] ?? null,
                        'city' => $data['city'] ?? null,
                        'lat' => isset($data['lat']) ? (float) $data['lat'] : null,
                        'lng' => isset($data['lon']) ? (float) $data['lon'] : null,
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silently fail - location data is not critical for login
            \Log::warning('Failed to get location data for IP: ' . $ip, ['error' => $e->getMessage()]);
        }

        return [];
    }
}
