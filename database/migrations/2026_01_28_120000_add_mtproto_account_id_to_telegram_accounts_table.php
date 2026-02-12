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
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->foreignId('mtproto_account_id')
                ->nullable()
                ->after('id')
                ->constrained('mtproto_telegram_accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->dropForeign(['mtproto_account_id']);
        });
    }
};
