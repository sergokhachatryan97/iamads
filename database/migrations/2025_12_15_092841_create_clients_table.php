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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->decimal('balance', 15, 8)->default(0);
            $table->decimal('spent', 15, 8)->default(0);
            $table->decimal('discount', 5, 2)->default(0);
            $table->json('rates')->nullable();
            $table->foreignId('staff_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('password');
            $table->rememberToken();
            $table->timestamp('last_auth')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
