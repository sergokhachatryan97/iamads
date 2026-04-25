<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const GUARD = 'staff';

    public function up(): void
    {
        $permission = Permission::findOrCreate('clients.access-all', self::GUARD);

        // Grant to super_admin
        $superAdmin = Role::where('name', 'super_admin')->where('guard_name', self::GUARD)->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permission);
        }

        // Grant to support (they need to see all clients/orders)
        $support = Role::where('name', 'support')->where('guard_name', self::GUARD)->first();
        if ($support) {
            $support->givePermissionTo($permission);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::where('name', 'clients.access-all')->where('guard_name', self::GUARD)->first();
        if ($permission) {
            $permission->delete();
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
