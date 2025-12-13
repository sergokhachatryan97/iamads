<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class UserService implements UserServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private RoleServiceInterface $roleService
    ) {
    }

    /**
     * Get paginated users with filters.
     *
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginatedUsers(array $filters): LengthAwarePaginator
    {
        return $this->userRepository->getPaginated($filters);
    }

    /**
     * Get all roles for filter dropdown.
     *
     * @return Collection
     */
    public function getAllRoles(): Collection
    {
        return $this->roleService->getAllRoles();
    }

    /**
     * Delete a user.
     *
     * @param User $user
     * @param int $currentUserId
     * @return bool
     * @throws \Exception
     */
    public function deleteUser(User $user, int $currentUserId): bool
    {
        // Prevent self-delete
        if ($user->id === $currentUserId) {
            throw new \Exception('You cannot delete your own account.');
        }

        // Delete user's avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        return $this->userRepository->delete($user);
    }

    /**
     * Resend verification email to user.
     *
     * @param User $user
     * @return void
     * @throws \Exception
     */
    public function resendVerificationEmail(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new \Exception('User email is already verified.');
        }

        $this->userRepository->sendVerificationEmail($user);
    }
}
