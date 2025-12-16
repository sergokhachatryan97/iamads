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
            ->select(['id', 'name', 'email', 'email_verified_at', 'avatar', 'created_at'])
            ->with('roles:id,name');

        // Search
        if (!empty($filters['q'])) {
            $search = trim($filters['q']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if (!empty($filters['role'])) {
            $role = trim($filters['role']);
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by verification status (safe handling)
        if (($filters['verified'] ?? null) === '1') {
            $query->whereNotNull('email_verified_at');
        } elseif (($filters['verified'] ?? null) === '0') {
            $query->whereNull('email_verified_at');
        }

        // Sorting (ALLOWLIST)
        $allowedSorts = ['id', 'name', 'email', 'email_verified_at', 'created_at'];
        $sort = $filters['sort'] ?? 'created_at';
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'created_at';
        }

        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        // Pagination (limit perPage)
        $allowedPerPage = [15, 25, 50, 100];
        $perPage = (int)($filters['perPage'] ?? 15);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 15;
        }

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

    /**
     * Get all users ordered by name.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return User::select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
    }
}
