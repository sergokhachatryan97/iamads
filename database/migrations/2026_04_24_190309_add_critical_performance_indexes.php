<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Critical indexes for high-frequency queries that cause MariaDB CPU spikes:
 *
 * - telegram_tasks(status, order_id): PreassignTelegramTasksJob runs every 30s
 * - telegram_account_link_states(account_phone, action, state): TelegramTaskClaimService at 1200+ req/s
 * - orders(client_id, created_at): Client dashboard stats
 * - orders(category_id, status): Client dashboard platform stats
 * - max_tasks(order_id, status): Max claim flow + dashboard
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->index(['status', 'order_id'], 'idx_tg_tasks_status_order');
        });

        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->index(['account_phone', 'action', 'state'], 'idx_tg_link_states_phone_action_state');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['client_id', 'created_at'], 'idx_orders_client_created');
            $table->index(['category_id', 'status'], 'idx_orders_category_status');
        });

        Schema::table('max_tasks', function (Blueprint $table) {
            $table->index(['order_id', 'status'], 'idx_max_tasks_order_status');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tg_tasks_status_order');
        });

        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->dropIndex('idx_tg_link_states_phone_action_state');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_client_created');
            $table->dropIndex('idx_orders_category_status');
        });

        Schema::table('max_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_max_tasks_order_status');
        });
    }
};
