<?php

namespace App\Services;

use App\Models\ClientLoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClientLoginLogServiceInterface
{
    /**
     * Get paginated login logs for a client with filters.
     *
     * @param int $clientId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedLogsForClient(int $clientId, array $filters = []): LengthAwarePaginator;

    /**
     * Get unique device types for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueDeviceTypes(int $clientId): Collection;

    /**
     * Get unique operating systems for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueOperatingSystems(int $clientId): Collection;

    /**
     * Get unique browsers for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueBrowsers(int $clientId): Collection;

    /**
     * Get unique countries for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getUniqueCountries(int $clientId): Collection;

    /**
     * Get distinct IPs for a client.
     *
     * @param int $clientId
     * @return Collection
     */
    public function getDistinctIps(int $clientId): Collection;

    /**
     * Find clients who have logged in from the same IPs as the given client.
     *
     * @param int $clientId
     * @return array Returns array with 'clientIps' and 'matches'
     */
    public function findMatchingIps(int $clientId): array;

    /**
     * Create a new login log.
     *
     * @param array $data
     * @return ClientLoginLog
     */
    public function createLog(array $data): ClientLoginLog;

    /**
     * Create a login log from request data (with device and location detection).
     *
     * @param \App\Models\Client $client
     * @param \Illuminate\Http\Request $request
     * @return ClientLoginLog
     */
    public function createLoginLogFromRequest(\App\Models\Client $client, \Illuminate\Http\Request $request): ClientLoginLog;
}
