<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('charge', 12, 4)->change();
            $table->decimal('cost', 12, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('charge', 12, 2)->change();
            $table->decimal('cost', 12, 2)->nullable()->change();
        });
    }
};
