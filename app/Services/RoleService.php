<?php

namespace App\Services;

use App\Repositories\RoleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleService implements RoleServiceInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository
    ) {
    }

    /**
     * Get all roles ordered by name.
     *
     * @return Collection
     */
    public function getAllRoles(): Collection
    {
        return $this->roleRepository->getAll();
    }

    /**
     * Get all permissions ordered by name.
     *
     * @return Collection
     */
    public function getAllPermissions(): Collection
    {
        return Permission::orderBy('name')->get();
    }

    /**
     * Update role permissions.
     *
     * @param Role $role
     * @param array $permissions
     * @return Role
     */
    public function updateRolePermissions(Role $role, array $permissions): Role
    {
        return $this->roleRepository->syncPermissions($role, $permissions);
    }
}
