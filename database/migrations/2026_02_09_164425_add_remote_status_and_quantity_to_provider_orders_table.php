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
        Schema::table('provider_orders', function (Blueprint $table) {
            $table->string('remote_status')->nullable()->after('status');
            $table->unsignedInteger('quantity')->nullable()->after('remote_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_orders', function (Blueprint $table) {
            $table->dropColumn(['remote_status', 'quantity']);
        });
    }
};
