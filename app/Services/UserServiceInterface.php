<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserServiceInterface
{
    /**
     * Get paginated users with filters.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedUsers(array $filters): LengthAwarePaginator;

    /**
     * Get all roles for filter dropdown.
     *
     * @return Collection
     */
    public function getAllRoles(): Collection;

    /**
     * Delete a user.
     *
     * @param User $user
     * @param int $currentUserId
     * @return bool
     * @throws \Exception
     */
    public function deleteUser(User $user, int $currentUserId): bool;

    /**
     * Resend verification email to user.
     *
     * @param User $user
     * @return void
     * @throws \Exception
     */
    public function resendVerificationEmail(User $user): void;
}
