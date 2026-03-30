<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('youtube_tasks', function (Blueprint $table) {
            $table->dropUnique('yt_tasks_acc_ord_link_act_uniq');
        });
    }

    public function down(): void
    {
        Schema::table('youtube_tasks', function (Blueprint $table) {
            $table->unique(
                ['account_identity', 'order_id', 'link_hash', 'action'],
                'yt_tasks_acc_ord_link_act_uniq'
            );
        });
    }
};
