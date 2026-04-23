<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalize service_id onto telegram_order_memberships and telegram_tasks
 * to eliminate expensive JOINs with orders in TelegramStatsController.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('telegram_order_memberships', 'service_id')) {
            Schema::table('telegram_order_memberships', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('order_id');
            });
        }

        if (! Schema::hasColumn('telegram_tasks', 'service_id')) {
            Schema::table('telegram_tasks', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id')->nullable()->after('order_id');
            });
        }

        // Backfill from orders
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('UPDATE telegram_order_memberships SET service_id = (SELECT service_id FROM orders WHERE orders.id = telegram_order_memberships.order_id) WHERE service_id IS NULL');
            DB::statement('UPDATE telegram_tasks SET service_id = (SELECT service_id FROM orders WHERE orders.id = telegram_tasks.order_id) WHERE service_id IS NULL');
        } else {
            DB::statement('UPDATE telegram_order_memberships m JOIN orders o ON o.id = m.order_id SET m.service_id = o.service_id WHERE m.service_id IS NULL');
            DB::statement('UPDATE telegram_tasks t JOIN orders o ON o.id = t.order_id SET t.service_id = o.service_id WHERE t.service_id IS NULL');
        }

        Schema::table('telegram_order_memberships', function (Blueprint $table) {
            $table->index(['service_id', 'state', 'subscribed_at'], 'idx_tg_memberships_svc_state_sub');
        });

        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->index(['service_id', 'status', 'updated_at'], 'idx_tg_tasks_svc_status_updated');
            $table->index(['service_id', 'created_at'], 'idx_tg_tasks_svc_created');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_order_memberships', function (Blueprint $table) {
            $table->dropIndex('idx_tg_memberships_svc_state_sub');
            $table->dropColumn('service_id');
        });

        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_tg_tasks_svc_status_updated');
            $table->dropIndex('idx_tg_tasks_svc_created');
            $table->dropColumn('service_id');
        });
    }
};
