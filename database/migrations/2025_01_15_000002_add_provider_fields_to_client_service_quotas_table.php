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
        Schema::table('client_service_quotas', function (Blueprint $table) {
            $table->json('provider_payload')->nullable()->after('expires_at');
            $table->timestamp('provider_sending_at')->nullable()->after('provider_payload');
            $table->text('provider_last_error')->nullable()->after('provider_sending_at');
            $table->timestamp('provider_last_error_at')->nullable()->after('provider_last_error');
            
            // Index for claim locking
            $table->index('provider_sending_at', 'idx_provider_sending_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_service_quotas', function (Blueprint $table) {
            $table->dropIndex('idx_provider_sending_at');
            $table->dropColumn([
                'provider_payload',
                'provider_sending_at',
                'provider_last_error',
                'provider_last_error_at',
            ]);
        });
    }
};
