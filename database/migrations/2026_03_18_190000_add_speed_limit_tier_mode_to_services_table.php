<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * When 'both': client chooses fast or super_fast (current behavior).
     * When 'fast' or 'super_fast': only that tier is offered, no selection needed.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('speed_limit_tier_mode', 20)->default('both')
                ->after('speed_limit_enabled')
                ->comment('both=choose, fast=fast only, super_fast=super_fast only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('speed_limit_tier_mode');
        });
    }
};
