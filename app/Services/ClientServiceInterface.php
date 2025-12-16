<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClientServiceInterface
{
    /**
     * Get paginated clients with filters.
     *
     * @param array $filters
     * @param \App\Models\User|null $user Current authenticated user for permission filtering
     * @return LengthAwarePaginator
     */
    public function getPaginatedClients(array $filters, ?\App\Models\User $user = null): LengthAwarePaginator;

    /**
     * Get all staff members for filter dropdown.
     *
     * @param \App\Models\User|null $user Current authenticated user for permission filtering
     * @return Collection
     */
    public function getAllStaff(?\App\Models\User $user = null): Collection;

    /**
     * Delete a client.
     *
     * @param Client $client
     * @param \App\Models\User|null $user Current authenticated user for permission checking
     * @return bool
     * @throws \Exception
     */
    public function deleteClient(Client $client, ?\App\Models\User $user = null): bool;
}

