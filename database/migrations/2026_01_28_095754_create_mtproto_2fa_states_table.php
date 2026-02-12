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
        Schema::create('mtproto_2fa_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->unique()->constrained('mtproto_telegram_accounts')->onDelete('cascade');
            $table->string('email_alias')->nullable();
            $table->text('encrypted_password')->nullable(); // Encrypted password using Laravel encryption
            $table->enum('status', ['pending', 'waiting_email', 'confirmed', 'failed'])->default('pending')->index();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mtproto_2fa_states');
    }
};
