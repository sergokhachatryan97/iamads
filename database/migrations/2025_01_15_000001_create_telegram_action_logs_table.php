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
        Schema::create('telegram_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_account_id')->constrained('telegram_accounts')->onDelete('cascade');
            $table->string('link_hash', 64)->index();
            $table->string('action', 50);
            $table->nullableMorphs('subject'); // subject_type, subject_id (for Order or ClientServiceQuota)
            $table->timestamp('performed_at')->useCurrent();
            
            // Unique constraint: same account cannot perform same action on same link twice
            $table->unique(['telegram_account_id', 'link_hash', 'action'], 'unique_account_link_action');
            
            // Index for querying by link and action
            $table->index(['link_hash', 'action'], 'idx_link_action');
            
            // Index for subject lookup
            $table->index(['subject_type', 'subject_id'], 'idx_subject');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_action_logs');
    }
};
