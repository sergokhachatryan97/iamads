<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Updates App category services to use target_type='app' so they appear in the staff services table.
     */
    public function up(): void
    {
        DB::table('services')
            ->whereIn('category_id', function ($query) {
                $query->select('id')
                    ->from('categories')
                    ->where('link_driver', 'app');
            })
            ->update(['target_type' => 'app']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('services')
            ->whereIn('category_id', function ($query) {
                $query->select('id')
                    ->from('categories')
                    ->where('link_driver', 'app');
            })
            ->update(['target_type' => 'channel']);
    }
};
