<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mtproto_telegram_accounts', function (Blueprint $table) {
            $table->unsignedInteger('premium_folder_id')->nullable()->after('phone_number');
            $table->string('premium_folder_title', 255)->nullable()->after('premium_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('mtproto_telegram_accounts', function (Blueprint $table) {
            $table->dropColumn(['premium_folder_id', 'premium_folder_title']);
        });
    }
};
