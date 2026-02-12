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
            $table->string('provider')->nullable()->after('priority')->index();
            $table->unsignedBigInteger('provider_service_id')->nullable()->after('provider')->index();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->unique(['provider', 'provider_service_id'], 'services_provider_provider_service_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropUnique('services_provider_provider_service_id_unique');
            $table->dropColumn(['provider', 'provider_service_id']);
        });
    }
};
