<?php

namespace App\Repositories;

use App\Models\ClientLoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClientLoginLogRepository implements ClientLoginLogRepositoryInterface
{
    /**
     * Get paginated login logs for a client with filters.
     *
     * @param int $clientId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedForClient(int $clientId, array $filters = []): LengthAwarePaginator
    {
        $query = ClientLoginLog::where('client_id', $clientId)
            ->orderBy('signed_in_at', 'desc');

        // Filter by IP
        if (!empty($filters['ip'])) {
            $query->where('ip', 'like', '%' . $filters['ip'] . '%');
        }

        // Filter by device_type
        if (!empty($filters['device_type'])) {
            $query->where('device_type', $filters['device_type']);
        }

        // Filter by OS
        if (!empty($filters['os'])) {
            $query->where('os', 'like', '%' . $filters['os'] . '%');
        }

        // Filter by browser
        if (!empty($filters['browser'])) {
            $query->where('browser', 'like', '%' . $filters['browser'] . '%');
        }

        // Filter by country
        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        // Filter by city
        if (!empty($filters['city'])) {
            $query->where('city', 'like', '%' . $filters['city'] . '%');
        }

        $perPage = $filters['per_page'] ?? 25;

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get unique device types for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueDeviceTypes(int $clientId): Collection
    {
        return ClientLoginLog::where('client_id', $clientId)
            ->whereNotNull('device_type')
            ->distinct()
            ->pluck('device_type')
            ->sort()
            ->values();
    }

    /**
     * Get unique operating systems for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueOperatingSystems(int $clientId): Collection
    {
        return ClientLoginLog::where('client_id', $clientId)
            ->whereNotNull('os')
            ->distinct()
            ->pluck('os')
            ->sort()
            ->values();
    }

    /**
     * Get unique browsers for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueBrowsers(int $clientId): Collection
    {
        return ClientLoginLog::where('client_id', $clientId)
            ->whereNotNull('browser')
            ->distinct()
            ->pluck('browser')
            ->sort()
            ->values();
    }

    /**
     * Get unique countries for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueCountries(int $clientId): Collection
    {
        return ClientLoginLog::where('client_id', $clientId)
            ->whereNotNull('country')
            ->distinct()
            ->pluck('country')
            ->sort()
            ->values();
    }

    /**
     * Get distinct IPs for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getDistinctIps(int $clientId): Collection
    {
        return ClientLoginLog::where('client_id', $clientId)
            ->distinct()
            ->pluck('ip');
    }

    /**
     * Find login logs by IPs.
     *
     * @param array $ips
     * @param int $excludeClientId
     * @return Collection
     */
    public function findByIps(array $ips, int $excludeClientId): Collection
    {
        return ClientLoginLog::whereIn('ip', $ips)
            ->where('client_id', '!=', $excludeClientId)
            ->with('client:id,name,email')
            ->get();
    }

    /**
     * Create a new login log.
     *
     * @param array $data
     * @return ClientLoginLog
     */
    public function create(array $data): ClientLoginLog
    {
        return ClientLoginLog::create($data);
    }

    /**
     * Find a login log by ID.
     *
     * @param int $id
     * @return ClientLoginLog|null
     */
    public function findById(int $id): ?ClientLoginLog
    {
        return ClientLoginLog::find($id);
    }
}
