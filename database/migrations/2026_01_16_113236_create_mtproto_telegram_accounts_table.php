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
        Schema::create('mtproto_telegram_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('session_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_probe')->default(false);
            $table->string('phone_number', 32)->nullable();
            $table->unsignedInteger('subscription_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('cooldown_until')->nullable();
            $table->unsignedInteger('fail_count')->default(0);
            $table->string('last_error_code', 100)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'disabled_at', 'is_probe']);
            $table->index(['cooldown_until']);
            $table->index(['last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mtproto_telegram_accounts');
    }
};
