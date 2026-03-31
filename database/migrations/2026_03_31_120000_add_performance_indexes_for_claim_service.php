<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes to speed up TelegramTaskClaimService queries.
 * These are the hot paths called on every performer poll.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Orders: the main claim query filters by (status, execution_phase, remains)
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'execution_phase', 'remains'], 'idx_orders_status_phase_remains');
        });

        // TelegramTasks: folder_add/unsubscribe lookups by (order_id, action, status)
        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->index(['order_id', 'action', 'status'], 'idx_tg_tasks_order_action_status');
        });

        // TelegramOrderMemberships: in_flight count by (order_id, state)
        Schema::table('telegram_order_memberships', function (Blueprint $table) {
            $table->index(['order_id', 'state'], 'idx_tg_memberships_order_state');
        });

        // TelegramAccountLinkStates: active subscribed count by (account_phone, state)
        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->index(['account_phone', 'state'], 'idx_tg_link_states_phone_state');
        });

        // TelegramFolderMemberships: removed lookup by (folder_id, status)
        Schema::table('telegram_folder_memberships', function (Blueprint $table) {
            $table->index(['folder_id', 'status'], 'idx_tg_folder_memberships_folder_status');
        });

        // Orders: YouTube/App claim — filter by (category_id, status) then range scan on id, check remains
        // Covers: WHERE category_id IN (...) AND status IN (...) AND remains > 0 AND id >= X ORDER BY id
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['category_id', 'status', 'id', 'remains'], 'idx_orders_cat_status_id_remains');
        });

        // YouTubeTasks: in-flight count by (order_id, status)
        Schema::table('youtube_tasks', function (Blueprint $table) {
            $table->index(['order_id', 'status'], 'idx_yt_tasks_order_status');
        });

        // AppTasks: same pattern as YouTube
        Schema::table('app_tasks', function (Blueprint $table) {
            $table->index(['order_id', 'status'], 'idx_app_tasks_order_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_status_phase_remains');
            $table->dropIndex('idx_orders_cat_status_id_remains');
        });
        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tg_tasks_order_action_status');
        });
        Schema::table('telegram_order_memberships', function (Blueprint $table) {
            $table->dropIndex('idx_tg_memberships_order_state');
        });
        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->dropIndex('idx_tg_link_states_phone_state');
        });
        Schema::table('telegram_folder_memberships', function (Blueprint $table) {
            $table->dropIndex('idx_tg_folder_memberships_folder_status');
        });
        Schema::table('youtube_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_yt_tasks_order_status');
        });
        Schema::table('app_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_app_tasks_order_status');
        });
    }
};
