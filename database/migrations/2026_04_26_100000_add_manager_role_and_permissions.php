<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GUARD = 'staff';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure all permissions referenced across routes/middleware exist in DB
        $allPermissions = [
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

        foreach ($allPermissions as $perm) {
            Permission::findOrCreate($perm, self::GUARD);
        }

        // Sync super_admin with all permissions
        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', self::GUARD)->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::where('guard_name', self::GUARD)->get());
        }

        // Sync admin with its permissions
        $admin = Role::where('name', 'admin')->where('guard_name', self::GUARD)->first();
        if ($admin) {
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
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No destructive rollback — permissions are additive
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
