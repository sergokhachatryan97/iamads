<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove legacy "both" (client picks fast vs super_fast); default is fast-only.
     */
    public function up(): void
    {
        DB::table('services')
            ->where('speed_limit_tier_mode', 'both')
            ->update(['speed_limit_tier_mode' => 'fast']);
    }

    public function down(): void
    {
        // Cannot restore which services were "both" vs "fast"
    }
};
