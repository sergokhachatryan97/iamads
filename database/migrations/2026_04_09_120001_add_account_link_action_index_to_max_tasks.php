<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('max_tasks', function (Blueprint $table) {
            $table->index(
                ['account_identity', 'link_hash', 'action', 'status'],
                'idx_max_tasks_account_link_action_status'
            );
        });
    }

    public function down(): void
    {
        Schema::table('max_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_max_tasks_account_link_action_status');
        });
    }
};
