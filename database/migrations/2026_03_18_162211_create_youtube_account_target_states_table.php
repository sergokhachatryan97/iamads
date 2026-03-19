<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_account_target_states', function (Blueprint $table) {
            $table->id();
            $table->string('account_identity', 255);
            $table->string('action', 64);
            $table->string('target_type', 32);
            $table->string('normalized_target', 512);
            $table->string('target_hash', 64);
            $table->string('state', 32)->default('in_progress')->index();
            $table->string('last_task_id', 26)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(
                ['account_identity', 'action', 'target_hash'],
                'youtube_account_target_unique'
            );
            $table->index(['state', 'target_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_account_target_states');
    }
};
