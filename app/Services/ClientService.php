<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use App\Repositories\ClientRepositoryInterface;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ClientService implements ClientServiceInterface
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * Get paginated clients with filters.
     *
     * @param array $filters
     * @param \App\Models\User|null $user Current authenticated user for permission filtering
     * @return LengthAwarePaginator
     */
    public function getPaginatedClients(array $filters, ?\App\Models\User $user = null): LengthAwarePaginator
    {
        return $this->clientRepository->getPaginated($filters, $user);
    }

    /**
     * Get all staff members for filter dropdown.
     *
     * @param \App\Models\User|null $user Current authenticated user for permission filtering
     * @return Collection
     */
    public function getAllStaff(?\App\Models\User $user = null): Collection
    {
        // Non-super_admin can only see themselves in the filter
        if ($user && !$user->hasRole('super_admin')) {
            // Return an Eloquent Collection containing only this user
            return User::where('id', $user->id)->get();
        }
        
        return $this->userRepository->getAll();
    }

    /**
     * Delete a client.
     *
     * @param Client $client
     * @param \App\Models\User|null $user Current authenticated user for permission checking
     * @return bool
     * @throws \Exception
     */
    public function deleteClient(Client $client, ?\App\Models\User $user = null): bool
    {
        // Permission check: non-super_admin can only delete their own clients
        if ($user && !$user->hasRole('super_admin') && $client->staff_id !== $user->id) {
            throw new \Exception('You do not have permission to delete this client.');
        }

        return $this->clientRepository->delete($client);
    }
}

