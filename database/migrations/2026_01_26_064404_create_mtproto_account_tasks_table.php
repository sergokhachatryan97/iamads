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
        Schema::create('mtproto_account_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');

            $table->index('account_id', 'idx_mtproto_account_tasks_account_id');

            $table->foreign('account_id', 'fk_mtproto_account_tasks_account_id')
                ->references('id')
                ->on('mtproto_telegram_accounts')
                ->cascadeOnDelete();
            $table->string('task_type', 50)->index();
            $table->json('payload_json')->nullable();
            $table->enum('status', ['pending', 'running', 'done', 'failed', 'retry'])->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('retry_at')->nullable()->index();
            $table->string('last_error_code', 100)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Unique constraint: one task per account per task_type (prevents duplicates)
            $table->unique(['account_id', 'task_type']);

            // Index for efficient querying
            $table->index(['status', 'retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mtproto_account_tasks');
    }
};
