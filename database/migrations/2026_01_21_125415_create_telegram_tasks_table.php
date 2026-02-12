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
        Schema::create('telegram_tasks', function (Blueprint $table) {
            $table->string('id', 26)->primary(); // ULID as task_id
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('action', 50);
            $table->string('link_hash', 64)->index();
            $table->foreignId('telegram_account_id')->nullable()->constrained('telegram_accounts')->onDelete('set null');
            $table->string('provider_account_id')->nullable()->index(); // External provider account ID
            $table->enum('status', ['queued', 'leased', 'pending', 'done', 'failed'])->default('queued')->index();
            $table->timestamp('leased_until')->nullable();
            $table->unsignedInteger('attempt')->default(0);
            $table->json('payload'); // link, post_id, per_call, meta
            $table->json('result')->nullable(); // Provider result
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['status', 'leased_until']);
            $table->index(['order_id', 'status']);
            $table->index(['telegram_account_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_tasks');
    }
};
