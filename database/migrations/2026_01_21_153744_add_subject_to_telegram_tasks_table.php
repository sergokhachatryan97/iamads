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
        Schema::table('telegram_tasks', function (Blueprint $table) {
            // Make order_id nullable to support quotas
            $table->foreignId('order_id')->nullable()->change();
            
            // Add polymorphic subject columns
            $table->string('subject_type')->nullable()->after('order_id');
            $table->unsignedBigInteger('subject_id')->nullable()->after('subject_type');
            
            // Add index for subject lookups
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_tasks', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropColumn(['subject_type', 'subject_id']);
            // Note: order_id nullable change cannot be easily reverted without data loss
            // In production, you may want to handle this differently
        });
    }
};
