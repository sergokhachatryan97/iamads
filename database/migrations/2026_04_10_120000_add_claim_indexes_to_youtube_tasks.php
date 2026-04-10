<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('youtube_tasks', function (Blueprint $table) {
            // Speeds up hasStepConflict() — finds in-flight tasks for the same
            // (account, link) across all orders.
            $table->index(
                ['account_identity', 'link_hash', 'status'],
                'yt_tasks_acc_link_status_idx'
            );

            // Speeds up the watch-time cooldown lookup in YouTubeTaskClaimService::claim()
            // — find recent leased watch tasks for an account.
            $table->index(
                ['account_identity', 'action', 'status', 'created_at'],
                'yt_tasks_acc_act_status_created_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('youtube_tasks', function (Blueprint $table) {
            $table->dropIndex('yt_tasks_acc_link_status_idx');
            $table->dropIndex('yt_tasks_acc_act_status_created_idx');
        });
    }
};
