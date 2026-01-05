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
        Schema::create('export_files', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // e.g. orders, services, transactions
            $table->string('format'); // csv or xlsx
            $table->json('filters')->nullable(); // selected filters
            $table->json('columns')->nullable(); // selected columns
            $table->string('status')->default('pending'); // pending, processing, ready, failed
            $table->string('file_disk')->default('local');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('rows_count')->nullable();
            $table->text('error')->nullable();
            $table->string('created_by_type'); // polymorphic: User/Client/Staff
            $table->unsignedBigInteger('created_by_id');
            $table->timestamps();

            // Indexes
            $table->index(['module', 'status']);
            $table->index('created_at');
            $table->index(['created_by_type', 'created_by_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_files');
    }
};
