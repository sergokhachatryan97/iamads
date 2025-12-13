<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Get paginated users with filters.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->with('roles:id,name')
            ->select(['id', 'name', 'email', 'email_verified_at', 'avatar', 'created_at']);

        // Search
        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (!empty($filters['role'])) {
            $query->role($filters['role']);
        }

        // Filter by verification status
        if (isset($filters['verified'])) {
            if ($filters['verified'] === '1') {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
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
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Delete a user.
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Send email verification notification to user.
     *
     * @param User $user
     * @return void
     */
    public function sendVerificationEmail(User $user): void
    {
        $user->sendEmailVerificationNotification();
    }
}
