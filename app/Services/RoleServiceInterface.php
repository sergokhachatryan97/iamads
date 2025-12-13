<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleServiceInterface
{
    /**
     * Get all roles ordered by name.
     *
     * @return Collection
     */
    public function getAllRoles(): Collection;

    /**
     * Get all permissions ordered by name.
     *
     * @return Collection
     */
    public function getAllPermissions(): Collection;

    /**
     * Update role permissions.
     *
     * @param Role $role
     * @param array $permissions
     * @return Role
     */
    public function updateRolePermissions(Role $role, array $permissions): Role;
}
