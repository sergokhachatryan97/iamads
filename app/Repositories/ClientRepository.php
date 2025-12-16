<?php

namespace App\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * Get paginated clients with filters.
     *
     * @param array $filters
     * @param \App\Models\User|null $user Current authenticated user for permission filtering
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters, ?\App\Models\User $user = null): LengthAwarePaginator
    {
        $query = Client::query()
            ->with('staff:id,name,email')
            ->select(['id', 'name', 'email', 'balance', 'spent', 'discount', 'staff_id', 'last_auth', 'created_at']);

        // Permission-based filtering: non-super_admin can only see their own clients
        if ($user && !$user->hasRole('super_admin')) {
            $query->where('staff_id', $user->id);
        }

        // Search
        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by staff member
        // Only apply staff_id filter if it's explicitly set and not empty
        if (isset($filters['staff_id']) && $filters['staff_id'] !== '' && $filters['staff_id'] !== null) {
            if ($filters['staff_id'] === 'null' || $filters['no_staff']) {
                // Filter for clients without staff
                $query->whereNull('staff_id');
            } else {
                // Cast to integer to ensure proper comparison
                $staffId = (int) $filters['staff_id'];
                if ($staffId > 0) {
                    $query->where('staff_id', $staffId);
                }
            }
        }

        // Filter by balance range
        if (!empty($filters['balance_min'])) {
            $query->where('balance', '>=', $filters['balance_min']);
        }
        if (!empty($filters['balance_max'])) {
            $query->where('balance', '<=', $filters['balance_max']);
        }
        // Validate that balance_max >= balance_min if both are set
        if (!empty($filters['balance_min']) && !empty($filters['balance_max']) && $filters['balance_max'] < $filters['balance_min']) {
            // If max is less than min, swap them or ignore max
            $query->where('balance', '>=', $filters['balance_min']);
        }

        // Filter by spent range
        if (!empty($filters['spent_min'])) {
            $query->where('spent', '>=', $filters['spent_min']);
        }
        if (!empty($filters['spent_max'])) {
            $query->where('spent', '<=', $filters['spent_max']);
        }
        // Validate that spent_max >= spent_min if both are set
        if (!empty($filters['spent_min']) && !empty($filters['spent_max']) && $filters['spent_max'] < $filters['spent_min']) {
            // If max is less than min, swap them or ignore max
            $query->where('spent', '>=', $filters['spent_min']);
        }

        // Filter by last_auth date
        if (!empty($filters['date_filter'])) {
            switch ($filters['date_filter']) {
                case 'today':
                    $query->whereDate('last_auth', now()->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('last_auth', now()->subDay()->toDateString());
                    break;
                case '7days':
                    $query->where('last_auth', '>=', now()->subDays(7)->startOfDay());
                    break;
                case '30days':
                    $query->where('last_auth', '>=', now()->subDays(30)->startOfDay());
                    break;
                case '90days':
                    $query->where('last_auth', '>=', now()->subDays(90)->startOfDay());
                    break;
                case 'custom':
                    if (!empty($filters['date_from'])) {
                        $query->where('last_auth', '>=', \Carbon\Carbon::parse($filters['date_from'])->startOfDay());
                    }
                    if (!empty($filters['date_to'])) {
                        $query->where('last_auth', '<=', \Carbon\Carbon::parse($filters['date_to'])->endOfDay());
                    }
                    break;
            }
        }

        // Filter by created_at date
        if (!empty($filters['created_at_filter'])) {
            switch ($filters['created_at_filter']) {
                case 'today':
                    $query->whereDate('created_at', now()->toDateString());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', now()->subDay()->toDateString());
                    break;
                case '7days':
                    $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
                    break;
                case '30days':
                    $query->where('created_at', '>=', now()->subDays(30)->startOfDay());
                    break;
                case '90days':
                    $query->where('created_at', '>=', now()->subDays(90)->startOfDay());
                    break;
                case 'custom':
                    if (!empty($filters['created_at_from'])) {
                        $query->where('created_at', '>=', \Carbon\Carbon::parse($filters['created_at_from'])->startOfDay());
                    }
                    if (!empty($filters['created_at_to'])) {
                        $query->where('created_at', '<=', \Carbon\Carbon::parse($filters['created_at_to'])->endOfDay());
                    }
                    break;
            }
        }

        // Sorting
        $sort = $filters['sort'] ?? 'created_at';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        // Pagination
        $perPage = $filters['perPage'] ?? 15;
        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Find a client by ID.
     *
     * @param int $id
     * @return Client|null
     */
    public function findById(int $id): ?Client
    {
        return Client::find($id);
    }

    /**
     * Delete a client.
     *
     * @param Client $client
     * @return bool
     */
    public function delete(Client $client): bool
    {
        return $client->delete();
    }
}

