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
        Schema::table('mtproto_telegram_accounts', function (Blueprint $table) {
            $table->boolean('is_inspect')->default(true);   // getInfo / checkChatInvite
            $table->boolean('is_heavy')->default(false);    // join / sendMessage / botStart

            // daily cap for heavy actions
            $table->unsignedInteger('daily_heavy_cap')->default(120);   // safe default
            $table->unsignedInteger('daily_heavy_used')->default(0);
            $table->dateTime('daily_heavy_reset_at')->nullable();

            // stats
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            // last error reason (optional but helpful)
            $table->string('last_error', 120)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mtproto_telegram_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'is_inspect','is_heavy',
                'daily_heavy_cap','daily_heavy_used','daily_heavy_reset_at',
                'success_count','failure_count',
                'last_error',
            ]);
        });
    }
};
