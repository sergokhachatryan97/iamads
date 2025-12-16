<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Get paginated users with filters.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginated(array $filters): LengthAwarePaginator;

    /**
     * Find a user by ID.
     *
     * @param int $id
     * @return User|null
     */
    public function findById(int $id): ?User;

    /**
     * Delete a user.
     *
     * @param User $user
     * @return bool
     */
    public function delete(User $user): bool;

    /**
     * Send email verification notification to user.
     *
     * @param User $user
     * @return void
     */
    public function sendVerificationEmail(User $user): void;

    /**
     * Get all users ordered by name.
     *
     * @return Collection
     */
    public function getAll(): Collection;
}
