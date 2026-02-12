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
        Schema::table('services', function (Blueprint $table) {
            $table->decimal('rate_multiplier_fast', 6, 3)->default(1.000)->after('speed_multiplier_super_fast');
            $table->decimal('rate_multiplier_super_fast', 6, 3)->default(1.000)->after('rate_multiplier_fast');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['rate_multiplier_fast', 'rate_multiplier_super_fast']);
        });
    }
};
