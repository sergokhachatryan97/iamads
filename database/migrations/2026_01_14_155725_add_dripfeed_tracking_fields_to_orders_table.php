<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Check if dripfeed_enabled exists, if not add it
            if (!Schema::hasColumn('orders', 'dripfeed_enabled')) {
                $table->boolean('dripfeed_enabled')->default(false)->after('dripfeed_interval_unit');
            }

            // Add tracking fields
            $table->unsignedInteger('dripfeed_runs_total')->nullable()->after('dripfeed_enabled');
            $table->unsignedInteger('dripfeed_interval_minutes')->nullable()->after('dripfeed_runs_total');
            $table->unsignedInteger('dripfeed_run_index')->default(0)->after('dripfeed_interval_minutes');
            $table->unsignedInteger('dripfeed_delivered_in_run')->default(0)->after('dripfeed_run_index');
            $table->timestamp('dripfeed_next_run_at')->nullable()->after('dripfeed_delivered_in_run')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['dripfeed_next_run_at']);
            $table->dropColumn([
                'dripfeed_runs_total',
                'dripfeed_interval_minutes',
                'dripfeed_run_index',
                'dripfeed_delivered_in_run',
                'dripfeed_next_run_at',
            ]);

            // Only drop dripfeed_enabled if we added it
            if (Schema::hasColumn('orders', 'dripfeed_enabled')) {
                $table->dropColumn('dripfeed_enabled');
            }
        });
    }
};
