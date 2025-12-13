<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

class RoleRepository implements RoleRepositoryInterface
{
    /**
     * Get all roles ordered by name.
     *
     * @return Collection
     */
    public function getAll(): Collection
    {
        return Role::orderBy('name')->get();
    }

    /**
     * Find a role by ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function findById(int $id): ?Role
    {
        return Role::find($id);
    }

    /**
     * Sync permissions for a role.
     *
     * @param Role $role
     * @param array $permissions
     * @return Role
     */
    public function syncPermissions(Role $role, array $permissions): Role
    {
        $role->syncPermissions($permissions);
        return $role;
    }
}
