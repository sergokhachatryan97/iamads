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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('mode')->default('manual');
            $table->string('service_type')->default('default');
            $table->boolean('dripfeed_enabled')->default(false);
            $table->boolean('user_can_cancel')->default(false);
            $table->decimal('rate_per_1000', 12, 4)->default(0);
            $table->decimal('service_cost_per_1000', 12, 4)->nullable();
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->default(1);
            $table->boolean('deny_link_duplicates')->default(false);
            $table->unsignedSmallInteger('deny_duplicates_days')->default(90);
            $table->unsignedInteger('increment')->default(0);
            $table->boolean('start_count_parsing_enabled')->default(false);
            $table->string('count_type')->nullable();
            $table->boolean('auto_complete_enabled')->default(false);
            $table->boolean('refill_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['category_id', 'is_active']);
            $table->index(['mode', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
