<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_folder_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('mtproto_telegram_account_id')->constrained('mtproto_telegram_accounts')->cascadeOnDelete();
            $table->string('target_link', 2048);
            $table->string('target_link_hash', 64);
            $table->string('peer_type', 32)->nullable();
            $table->string('target_username', 255)->nullable();
            $table->string('target_peer_id', 128)->nullable();
            $table->unsignedInteger('folder_id');
            $table->string('folder_title', 255)->nullable();
            $table->timestamp('added_at')->nullable();
            $table->timestamp('remove_at');
            $table->string('status', 32)->default('active');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['status', 'remove_at']);
            $table->index('target_link_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_folder_memberships');
    }
};
