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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('mode')->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['provider', 'provider_order_id'], 'orders_provider_provider_order_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_provider_provider_order_id_unique');
            $table->dropColumn('provider');
        });
    }
};
