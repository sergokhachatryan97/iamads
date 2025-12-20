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
        Schema::create('client_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->onDelete('cascade');
            $table->timestamp('signed_in_at')->useCurrent()->index();
            $table->string('ip', 45)->index();
            $table->string('user_agent', 1024)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('device_type', 20)->nullable()->index();
            $table->string('os', 50)->nullable()->index();
            $table->string('browser', 50)->nullable()->index();
            $table->string('device_name', 100)->nullable();
            $table->index(['client_id', 'signed_in_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_login_logs');
    }
};
