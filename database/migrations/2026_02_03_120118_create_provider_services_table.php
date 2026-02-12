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
        Schema::create('provider_services', function (Blueprint $table) {
            $table->id();

            $table->string('provider_code', 50);
            $table->string('remote_service_id', 64);

            $table->string('name', 255);
            $table->string('type', 50)->nullable();
            $table->json('description')->nullable();
            $table->string('category', 255)->nullable();

            $table->decimal('rate', 12, 4)->nullable();
            $table->unsignedInteger('min')->nullable();
            $table->unsignedInteger('max')->nullable();

            $table->boolean('refill')->default(false);
            $table->boolean('cancel')->default(false);

            $table->string('currency', 8)->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['provider_code', 'remote_service_id']);
            $table->index(['provider_code', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_services');
    }
};
