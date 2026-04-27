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
            'users.view',
            'roles.manage',
            'pages.edit.any',
            'pages.edit.own',
            'orders.view',
            'orders.stats',
            'clients.view',
            'clients.access-all',
            'services.view',
            'services.create',
            'services.edit',
            'services.delete',
            'services.toggle-status',
            'payments.view',
            'activity-logs.view',
            'provider-order-stats.view',
            'socpanel-moderation.view',
            'settings.view',
            'exports.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        // Super Admin — all permissions
        $superAdmin = Role::firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => $guard,
        ]);
        $superAdmin->syncPermissions(
            Permission::where('guard_name', $guard)->get()
        );

        // Admin
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);
        $admin->syncPermissions([
            'users.invite',
            'users.view',
            'pages.edit.any',
            'orders.view',
            'orders.stats',
            'clients.view',
            'clients.access-all',
            'services.view',
            'services.create',
            'services.edit',
            'services.toggle-status',
            'payments.view',
            'activity-logs.view',
            'provider-order-stats.view',
            'settings.view',
            'exports.view',
        ]);

        // User
        $user = Role::firstOrCreate([
            'name' => 'user',
            'guard_name' => $guard,
        ]);
        $user->syncPermissions([
            'pages.edit.own',
        ]);
    }
}
