<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds up Staff\TelegramStatsController queries on memberships (state + date range)
 * and telegram_tasks (status + updated_at for completion counts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_order_memberships', function (Blueprint $table) {
            $table->index(['state', 'subscribed_at'], 'idx_tg_memberships_state_subscribed_at');
        });

        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->index(['status', 'updated_at'], 'idx_tg_tasks_status_updated_at');
            $table->index(['created_at'], 'idx_tg_tasks_created_at');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_order_memberships', function (Blueprint $table) {
            $table->dropIndex('idx_tg_memberships_state_subscribed_at');
        });

        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tg_tasks_status_updated_at');
            $table->dropIndex('idx_tg_tasks_created_at');
        });
    }
};
