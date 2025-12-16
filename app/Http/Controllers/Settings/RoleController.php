<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateRoleRequest;
use App\Services\RoleServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(
        private RoleServiceInterface $roleService
    ) {
    }

    /**
     * Display a listing of roles.
     */
    public function index(): View
    {
        $roles = $this->roleService->getAllRoles();

        return view('settings.roles.index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit(Role $role): View
    {
        $permissions = $this->roleService->getAllPermissions();

        return view('settings.roles.edit', [
            'role' => $role,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the specified role's permissions.
     */
    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $permissions = $request->input('permissions', []);

        $this->roleService->updateRolePermissions($role, $permissions);

        return redirect()->route('staff.settings.roles.index')
            ->with('status', 'role-updated');
    }
}
