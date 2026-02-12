<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_orders', function (Blueprint $table) {
            $table->index(['status', 'created_at']);
            $table->index(['user_remote_id', 'status', 'created_at']);
            $table->index(['remote_service_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('provider_orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex(['user_remote_id', 'status', 'created_at']);
            $table->dropIndex(['remote_service_id', 'status', 'created_at']);
        });
    }
};
