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
        Schema::create('telegram_unsubscribe_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_account_id')
                ->constrained('telegram_accounts')
                ->cascadeOnDelete();
            $table->string('link_hash', 64)->index();
            $table->timestamp('due_at')->index();
            $table->string('status', 20)->default('pending')->index(); // pending|processing|done|failed
            $table->string('provider_task_id', 255)->nullable()->index();
            $table->string('telegram_task_id', 26)->nullable()->index();
            $table->foreign('telegram_task_id')
                ->references('id')
                ->on('telegram_tasks')
                ->nullOnDelete();
            $table->text('error')->nullable();
            $table->string('subject_type')->nullable(); // e.g. App\Models\Order
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->timestamps();

            // Unique constraint: one task per account+link_hash+due_at
            // If due_at is deterministic (e.g., based on service duration), we can use unique(account_id, link_hash)
            $table->unique(['telegram_account_id', 'link_hash', 'due_at'], 'unique_account_link_due');
            $table->index(['status', 'due_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_unsubscribe_tasks');
    }
};
