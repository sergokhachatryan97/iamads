<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add execution_phase to orders for account-driven claim flow.
     * telegram_order_memberships.order_id references orders.id (no FK added here to allow existing data).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'execution_phase')) {
                $table->string('execution_phase', 20)->nullable()->index()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'execution_phase')) {
                $table->dropColumn('execution_phase');
            }
        });
    }
};
