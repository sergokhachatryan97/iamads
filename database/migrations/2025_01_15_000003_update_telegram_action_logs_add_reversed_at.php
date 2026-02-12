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
        Schema::table('telegram_action_logs', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('performed_at');
            $table->json('meta')->nullable()->after('reversed_at');
            
            // Add index for active performed queries (reversed_at IS NULL)
            $table->index(['telegram_account_id', 'action', 'reversed_at'], 'idx_account_action_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_action_logs', function (Blueprint $table) {
            $table->dropIndex('idx_account_action_active');
            $table->dropColumn(['reversed_at', 'meta']);
        });
    }
};
