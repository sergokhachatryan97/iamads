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
            $table->unsignedInteger('dripfeed_quantity')->nullable()->after('quantity');
            $table->unsignedInteger('dripfeed_interval')->nullable()->after('dripfeed_quantity');
            $table->string('dripfeed_interval_unit', 20)->nullable()->after('dripfeed_interval');
            $table->longText('comment_text')->nullable()->after('link');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['dripfeed_quantity', 'dripfeed_interval', 'dripfeed_interval_unit', 'comment_text']);
        });
    }
};
