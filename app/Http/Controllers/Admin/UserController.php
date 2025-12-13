<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexUserRequest;
use App\Models\User;
use App\Services\UserServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        private UserServiceInterface $userService
    ) {
    }

    /**
     * Display a listing of users.
     */
    public function index(IndexUserRequest $request): View
    {
        $filters = $request->filters();

        $users = $this->userService->getPaginatedUsers($filters);
        $roles = $this->userService->getAllRoles();

        if ($request->ajax()) {
            return view('admin.users.partials.table', compact('users'));
        }

        return view('admin.users.index', [
            'users'   => $users,
            'roles'   => $roles,
            'filters' => $filters,
        ]);
    }

    /**
     * Delete a user.
     */
    public function destroy(User $user): RedirectResponse
    {
        try {
            $this->userService->deleteUser($user, Auth::id());

            return redirect()->route('admin.users.index')
                ->with('status', 'user-deleted');
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(User $user): RedirectResponse
    {
        try {
            $this->userService->resendVerificationEmail($user);

            return redirect()->route('admin.users.index')
                ->with('status', 'verification-resent');
        } catch (\Exception $e) {
            return redirect()->route('admin.users.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}

