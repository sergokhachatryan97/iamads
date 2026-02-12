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
        Schema::create('provider_orders', function (Blueprint $table) {
            $table->id();

            $table->string('provider_code', 50);
            $table->string('remote_order_id');

            $table->string('remote_service_id')->nullable();
            $table->string('status', 50)->index();

            $table->decimal('charge', 12, 4)->nullable();
            $table->unsignedBigInteger('start_count')->nullable();
            $table->unsignedBigInteger('remains')->nullable();
            $table->string('currency', 8)->nullable();
            $table->string('link', 8)->nullable();

            $table->unsignedBigInteger('user_remote_id')->nullable();
            $table->string('user_login', 255)->nullable();

            $table->dateTime('fetched_at')->index();

            $table->timestamp('provider_last_error_at')->nullable();
            $table->timestamp('provider_sending_at')->nullable();
            $table->text('provider_last_error')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('provider_response')->nullable();

            $table->timestamps();

            $table->unique(['provider_code', 'remote_order_id']);
            $table->index(['provider_code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_orders');
    }
};
