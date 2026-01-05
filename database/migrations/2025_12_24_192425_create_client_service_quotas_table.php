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
        Schema::create('client_service_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained('subscription_plans')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->boolean('auto_renew')->default(false);
            $table->unsignedInteger('orders_left')->nullable();
            $table->unsignedBigInteger('quantity_left')->nullable();
            $table->string('link', 2048)->nullable()->after('quantity_left');
            $table->dateTime('expires_at');
            $table->timestamps();

            // Indexes
            $table->index(['client_id', 'service_id', 'expires_at']);
            $table->index(['client_id', 'subscription_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_service_quotas');
    }
};
