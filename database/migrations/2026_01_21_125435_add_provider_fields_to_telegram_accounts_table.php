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
            $table->string('provider_account_id')->nullable()->unique()->after('id');
            $table->json('meta')->nullable()->after('tags'); // Additional metadata from provider
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_accounts', function (Blueprint $table) {
            $table->dropColumn(['provider_account_id', 'meta']);
        });
    }
};
