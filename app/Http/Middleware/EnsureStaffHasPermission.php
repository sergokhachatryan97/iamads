<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = Auth::guard('staff')->user();

        if (!$user) {
            \Log::warning('EnsureStaffHasPermission: no staff user found', [
                'permissions' => $permissions,
                'url' => $request->url(),
                'session_id' => $request->session()?->getId(),
            ]);
            abort(403, 'Unauthorized.');
        }

        // Super admin bypasses all permission checks
        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if (!$user->hasPermissionTo($permission, 'staff')) {
                \Log::warning('EnsureStaffHasPermission: denied', [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'role' => $user->roles->pluck('name')->join(','),
                    'permission' => $permission,
                    'url' => $request->url(),
                ]);
                abort(403, 'You do not have the required permission.');
            }
        }

        return $next($request);
    }
}
