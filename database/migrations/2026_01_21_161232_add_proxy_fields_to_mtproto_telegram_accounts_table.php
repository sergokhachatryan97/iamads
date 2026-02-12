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
            $table->string('proxy_type', 20)->nullable()->after('session_path');
            $table->string('proxy_host')->nullable()->after('proxy_type');
            $table->unsignedInteger('proxy_port')->nullable()->after('proxy_host');
            $table->string('proxy_user')->nullable()->after('proxy_port');
            $table->string('proxy_pass')->nullable()->after('proxy_user');
            $table->string('proxy_secret')->nullable()->after('proxy_pass');
            $table->boolean('force_proxy')->default(false)->after('proxy_secret');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mtproto_telegram_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'proxy_type',
                'proxy_host',
                'proxy_port',
                'proxy_user',
                'proxy_pass',
                'proxy_secret',
                'force_proxy',
            ]);
        });
    }
};
