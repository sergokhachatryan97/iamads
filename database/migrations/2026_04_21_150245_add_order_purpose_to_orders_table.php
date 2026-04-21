<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_purpose', 16)->default('normal')->after('source');
        });

        // Create permission for staff order creation
        \Spatie\Permission\Models\Permission::findOrCreate('orders.create', 'staff');
        $superAdmin = \Spatie\Permission\Models\Role::findByName('super_admin', 'staff');
        $superAdmin->givePermissionTo('orders.create');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_purpose');
        });
    }
};
