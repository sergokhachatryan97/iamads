<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const GUARD = 'staff';

    public function up(): void
    {
        // Create support role
        $support = Role::firstOrCreate([
            'name' => 'support',
            'guard_name' => self::GUARD,
        ]);

        // Assign support-appropriate permissions
        $support->syncPermissions([
            'clients.view',
            'clients.edit',
            'clients.add-balance',
            'clients.deduct-balance',
            'clients.sign-ins',
            'orders.view',
            'orders.create',
            'orders.cancel',
            'payments.view',
            'services.view',
            'activity-logs.view',
        ]);

        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'support')->where('guard_name', self::GUARD)->first();
        if ($role) {
            $role->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
