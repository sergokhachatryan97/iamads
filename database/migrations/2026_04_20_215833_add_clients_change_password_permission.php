<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('clients.change-password', 'staff');

        $superAdmin = Role::findByName('super_admin', 'staff');
        $superAdmin->givePermissionTo('clients.change-password');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'clients.change-password')->where('guard_name', 'staff')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
