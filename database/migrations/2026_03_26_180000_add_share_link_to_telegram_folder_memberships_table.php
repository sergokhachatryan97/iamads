<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_folder_memberships', function (Blueprint $table) {
            $table->string('folder_share_link', 2048)->nullable()->after('folder_title');
            $table->string('folder_share_slug', 255)->nullable()->after('folder_share_link');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_folder_memberships', function (Blueprint $table) {
            $table->dropColumn(['folder_share_link', 'folder_share_slug']);
        });
    }
};
