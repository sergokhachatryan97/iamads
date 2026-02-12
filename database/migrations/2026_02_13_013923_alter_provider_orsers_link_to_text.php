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
            $table->text('link')->change();
        });
    }

    public function down(): void
    {
        Schema::table('provider_orders', function (Blueprint $table) {
            $table->string('link', 8)->change();
        });
    }
};
