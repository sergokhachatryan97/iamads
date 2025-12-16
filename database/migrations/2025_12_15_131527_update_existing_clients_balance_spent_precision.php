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
        Schema::table('clients', function (Blueprint $table) {
            // Update balance and spent to match main migration precision
            // decimal(15, 8) allows up to 99,999,999.99999999
            $table->decimal('balance', 15, 8)->default(0)->change();
            $table->decimal('spent', 15, 8)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Revert back to original precision
            $table->decimal('balance', 10, 2)->default(0)->change();
            $table->decimal('spent', 10, 2)->default(0)->change();
        });
    }
};


