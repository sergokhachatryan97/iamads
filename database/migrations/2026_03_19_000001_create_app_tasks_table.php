<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_tasks', function (Blueprint $table) {
            $table->string('id', 26)->primary();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('link', 2048);
            $table->string('account_identity', 255)->nullable();
            $table->string('action', 64)->default('download');
            $table->string('link_hash', 64)->nullable();
            $table->string('status', 32)->default('leased')->index();
            $table->string('target_hash', 64)->nullable();
            $table->timestamp('leased_until')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'leased_until']);
            $table->unique(
                ['account_identity', 'order_id', 'link_hash', 'action'],
                'app_tasks_acc_ord_link_act_uniq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_tasks');
    }
};
