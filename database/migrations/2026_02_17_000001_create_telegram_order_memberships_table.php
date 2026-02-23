<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_order_memberships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->index()->comment('orders.id');
            $table->string('account_phone', 32)->index();
            $table->string('link_hash', 64)->index();
            $table->text('link')->nullable();
            $table->string('state', 20)->default('in_progress')->index();
            $table->string('subscribed_task_id', 26)->nullable()->index();
            $table->string('unsubscribed_task_id', 26)->nullable()->index();
            $table->timestamp('subscribed_at')->nullable()->index();
            $table->timestamp('unsubscribed_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'account_phone', 'link_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_order_memberships');
    }
};
