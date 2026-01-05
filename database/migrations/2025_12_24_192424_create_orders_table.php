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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->nullable();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->foreignId('service_id')->constrained('services')->onDelete('cascade');
            $table->string('link', 2048)->nullable();
            $table->string('payment_source');
            $table->foreignId('subscription_id')->nullable()->constrained('subscription_plans')->onDelete('set null');
            $table->decimal('charge', 12, 2);
            $table->decimal('cost', 12, 2)->nullable();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('delivered')->default(0);
            $table->bigInteger('remains')->default(0);
            $table->unsignedBigInteger('start_count')->nullable();
            $table->string('status')->default('awaiting');
            $table->string('mode')->default('manual');
            $table->string('provider_order_id')->nullable()->index();
            $table->timestamp('sent_to_provider_at')->nullable();
            $table->timestamp('provider_last_error_at')->nullable();
            $table->timestamp('provider_sending_at')->nullable();
            $table->text('provider_last_error')->nullable();
            $table->json('provider_payload')->nullable();
            $table->json('provider_response')->nullable();
            $table->json('provider_status_response')->nullable();
            $table->json('provider_webhook_payload')->nullable();
            $table->text('provider_webhook_last_error')->nullable();
            $table->timestamp('provider_webhook_received_at')->nullable();
            $table->timestamp('provider_last_polled_at')->nullable();
            $table->timestamp('provider_status_sync_lock_at')->nullable();
            $table->string('provider_status_sync_lock_owner')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['client_id', 'status']);
            $table->index(['service_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
