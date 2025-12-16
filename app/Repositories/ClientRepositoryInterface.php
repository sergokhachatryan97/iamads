<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ClientRepositoryInterface
{
    /**
     * Get paginated clients with filters.
     *
     * @param array $filters
     * @param \App\Models\User|null $user Current authenticated user for permission filtering
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters, ?\App\Models\User $user = null): LengthAwarePaginator;

    /**
     * Find a client by ID.
     *
     * @param int $id
     * @return Client|null
     */
    public function findById(int $id): ?Client;

    /**
     * Delete a client.
     *
     * @param Client $client
     * @return bool
     */
    public function delete(Client $client): bool;
}

