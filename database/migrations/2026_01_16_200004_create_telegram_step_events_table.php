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
//        Schema::create('telegram_step_events', function (Blueprint $table) {
//            $table->id();
//            $table->nullableMorphs('subject'); // subject_type, subject_id (Order or ClientServiceQuota)
//            $table->foreignId('telegram_account_id')->constrained('telegram_accounts')->onDelete('cascade');
//            $table->string('action', 50);
//            $table->string('link_hash', 64);
//            $table->boolean('ok');
//            $table->string('error', 512)->nullable();
//            $table->unsignedInteger('per_call')->default(1);
//            $table->unsignedInteger('retry_after')->nullable();
//            $table->timestamp('performed_at');
//            $table->json('extra')->nullable();
//            $table->timestamps();
//
//            // Indexes for efficient queries
//            $table->index(['subject_type', 'subject_id'], 'idx_subject');
//            $table->index(['link_hash', 'action'], 'idx_link_action');
//            $table->index(['telegram_account_id', 'performed_at'], 'idx_account_performed');
//            $table->index(['ok', 'performed_at'], 'idx_ok_performed');
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::dropIfExists('telegram_step_events');
    }
};
