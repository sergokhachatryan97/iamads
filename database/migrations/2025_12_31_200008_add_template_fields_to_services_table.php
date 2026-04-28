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
            $table->string('template_key', 50)->nullable()->after('service_type')->index();
            $table->unsignedSmallInteger('duration_days')->nullable()->after('template_key');
            $table->json('template_snapshot')->nullable()->after('duration_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['template_key']);
            $table->dropColumn(['template_key', 'duration_days', 'template_snapshot']);
        });
    }
};
