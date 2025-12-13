<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    /**
     * Get all roles ordered by name.
     *
     * @return Collection
     */
    public function getAll(): Collection;

    /**
     * Find a role by ID.
     *
     * @param int $id
     * @return Role|null
     */
    public function findById(int $id): ?Role;

    /**
     * Sync permissions for a role.
     *
     * @param Role $role
     * @param array $permissions
     * @return Role
     */
    public function syncPermissions(Role $role, array $permissions): Role;
}
