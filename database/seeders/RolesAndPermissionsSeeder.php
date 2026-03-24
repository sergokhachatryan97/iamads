<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'staff';

        $permissions = [
            'users.invite',
            'roles.manage',
            'pages.edit.any',
            'pages.edit.own',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => $guard,
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $user = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => $guard,
        ]);

        $superAdmin->syncPermissions(
            Permission::where('guard_name', $guard)->get()
        );

        $admin->syncPermissions([
            'users.invite',
            'pages.edit.any',
        ]);

        $user->syncPermissions([
            'pages.edit.own',
        ]);
    }
}
