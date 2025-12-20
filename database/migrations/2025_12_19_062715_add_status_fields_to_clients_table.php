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
        Schema::table('clients', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('last_auth');
            $table->timestamp('email_verified_at')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('email_verified_at');
            $table->timestamp('malicious_at')->nullable()->after('suspended_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'email_verified_at', 'suspended_at', 'malicious_at']);
        });
    }
};
