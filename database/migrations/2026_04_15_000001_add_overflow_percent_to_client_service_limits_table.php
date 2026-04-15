<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_service_limits', function (Blueprint $table) {
            $table->decimal('overflow_percent', 5, 2)->nullable()->after('increment');
        });
    }

    public function down(): void
    {
        Schema::table('client_service_limits', function (Blueprint $table) {
            $table->dropColumn('overflow_percent');
        });
    }
};
