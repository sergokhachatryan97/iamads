<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes to speed up StaffDashboardController queries:
 * - orders: refill/test ID lookups, test money aggregation
 * - payments: daily payments chart
 * - clients: new users chart, admin stats staff mapping
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['service_id', 'order_purpose'], 'idx_orders_svc_purpose');
            $table->index(['order_purpose', 'created_at'], 'idx_orders_purpose_created');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_at'], 'idx_payments_status_paid_at');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->index(['staff_id'], 'idx_clients_staff_id');
            $table->index(['created_at'], 'idx_clients_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_svc_purpose');
            $table->dropIndex('idx_orders_purpose_created');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_status_paid_at');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_clients_staff_id');
            $table->dropIndex('idx_clients_created_at');
        });
    }
};
