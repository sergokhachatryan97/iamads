<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('api_enabled')->default(false)->after('remember_token');
            $table->string('api_key')->nullable()->unique()->after('api_enabled');
            $table->timestamp('api_key_generated_at')->nullable()->after('api_key');
            $table->timestamp('api_last_used_at')->nullable()->after('api_key_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'api_enabled',
                'api_key',
                'api_key_generated_at',
                'api_last_used_at',
            ]);
        });
    }
};
