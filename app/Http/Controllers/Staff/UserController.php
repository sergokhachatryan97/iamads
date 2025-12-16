<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\IndexUserRequest;
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
    public function index(IndexUserRequest $request)
    {
        $filters = $request->filters();
        $users = $this->userService->getPaginatedUsers($filters);
        $roles = $this->userService->getAllRoles();

        // Return only table partial for AJAX requests
        if ($request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return response()->view('staff.users.partials.table', compact('users'))
                ->header('Content-Type', 'text/html; charset=utf-8');
        }

        return view('staff.users.index', [
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

            return redirect()->route('staff.users.index')
                ->with('status', 'user-deleted');
        } catch (\Exception $e) {
            return redirect()->route('staff.users.index')
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

            return redirect()->route('staff.users.index')
                ->with('status', 'verification-resent');
        } catch (\Exception $e) {
            return redirect()->route('staff.users.index')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }
}

