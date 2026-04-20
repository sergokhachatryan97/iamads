<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const GUARD = 'staff';

    private const PERMISSIONS = [
        // Clients
        'clients.view',
        'clients.edit',
        'clients.delete',
        'clients.add-balance',
        'clients.deduct-balance',
        'clients.suspend',
        'clients.assign-staff',
        'clients.change-password',
        'clients.sign-ins',

        // Orders
        'orders.view',
        'orders.cancel',
        'orders.bulk-action',
        'orders.stats',
        'orders.export',

        // Payments
        'payments.view',
        'payments.update-status',

        // Services
        'services.view',
        'services.create',
        'services.edit',
        'services.delete',
        'services.toggle-status',

        // Categories
        'categories.create',
        'categories.edit',
        'categories.toggle-status',
        'categories.reorder',

        // Subscription Plans
        'subscriptions.view',
        'subscriptions.create',
        'subscriptions.edit',
        'subscriptions.delete',

        // Exports
        'exports.view',
        'exports.create',
        'exports.download',

        // Settings (super_admin by default, but assignable)
        'settings.view',
        'settings.roles',
        'settings.invitations',
        'settings.referral',

        // Users Management
        'users.view',
        'users.edit',
        'users.delete',

        // Statistics & Reports
        'telegram-stats.view',
        'provider-order-stats.view',
        'provider-order-stats.export',
        'activity-logs.view',
        'socpanel-moderation.view',
    ];

    public function up(): void
    {
        // Create all permissions
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, self::GUARD);
        }

        // Grant all permissions to super_admin role
        $superAdmin = Role::findByName('super_admin', self::GUARD);
        $superAdmin->syncPermissions(self::PERMISSIONS);

        // Grant basic permissions to staff role if it exists
        try {
            $staff = Role::findByName('staff', self::GUARD);
            $staff->syncPermissions([
                'clients.view',
                'clients.edit',
                'clients.add-balance',
                'clients.sign-ins',
                'orders.view',
                'payments.view',
                'services.view',
                'exports.view',
                'exports.download',
            ]);
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // staff role doesn't exist yet, skip
        }

        // Clear cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::where('name', $permission)->where('guard_name', self::GUARD)->delete();
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
