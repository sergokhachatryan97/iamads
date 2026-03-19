<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_action_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->index(); // 'telegram' | 'youtube'
            $table->string('account_identifier', 255)->index(); // telegram_account_id (string) or account_identity
            $table->string('target_hash', 64)->index();
            $table->string('action', 50)->index();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['provider', 'account_identifier', 'target_hash', 'action'],
                'provider_action_logs_unique_per_account_target_action'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_action_logs');
    }
};
