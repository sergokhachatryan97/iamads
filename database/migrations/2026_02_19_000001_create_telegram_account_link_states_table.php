<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Global per (account_phone, link_hash) state for claim flow.
     * States: in_progress | subscribed | unsubscribed | failed.
     * Used to avoid assigning a new subscribe task for the same phone+link while one is in progress or already subscribed.
     */
    public function up(): void
    {
        Schema::create('telegram_account_link_states', function (Blueprint $table) {
            $table->id();
            $table->string('account_phone', 32)->index();
            $table->string('link_hash', 64)->index();
            $table->string('state', 20)->default('in_progress')->index();
            $table->string('last_task_id', 26)->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['account_phone', 'link_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_account_link_states');
    }
};
