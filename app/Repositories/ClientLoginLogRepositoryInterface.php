<?php

namespace App\Repositories;

use App\Models\ClientLoginLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClientLoginLogRepositoryInterface
{
    /**
     * Get paginated login logs for a client with filters.
     *
     * @param int $clientId
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedForClient(int $clientId, array $filters = []): LengthAwarePaginator;

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
     * Find login logs by IPs.
     *
     * @param array $ips
     * @param int $excludeClientId
     * @return Collection
     */
    public function findByIps(array $ips, int $excludeClientId): Collection;

    /**
     * Create a new login log.
     *
     * @param array $data
     * @return ClientLoginLog
     */
    public function create(array $data): ClientLoginLog;

    /**
     * Find a login log by ID.
     *
     * @param int $id
     * @return ClientLoginLog|null
     */
    public function findById(int $id): ?ClientLoginLog;
}
