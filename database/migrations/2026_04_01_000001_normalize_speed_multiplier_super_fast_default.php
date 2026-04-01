<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Legacy placeholder for fast-only services used 1.00; canonical default for super fast is 2.
     */
    public function up(): void
    {
        DB::table('services')
            ->where('speed_limit_enabled', true)
            ->where('speed_multiplier_super_fast', '<=', 1)
            ->update(['speed_multiplier_super_fast' => 2]);
    }

    public function down(): void
    {
        //
    }
};
