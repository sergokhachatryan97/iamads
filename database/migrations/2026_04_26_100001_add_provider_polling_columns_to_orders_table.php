<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'provider_last_polled_at')) {
                $table->timestamp('provider_last_polled_at')->nullable()->after('provider_last_error_at');
            }
            if (! Schema::hasColumn('orders', 'provider_status_sync_lock_at')) {
                $table->timestamp('provider_status_sync_lock_at')->nullable()->after('provider_last_polled_at');
            }
            if (! Schema::hasColumn('orders', 'provider_status_sync_lock_owner')) {
                $table->string('provider_status_sync_lock_owner')->nullable()->after('provider_status_sync_lock_at');
            }
            if (! Schema::hasColumn('orders', 'provider_webhook_payload')) {
                $table->json('provider_webhook_payload')->nullable()->after('provider_status_sync_lock_owner');
            }
            if (! Schema::hasColumn('orders', 'provider_webhook_received_at')) {
                $table->timestamp('provider_webhook_received_at')->nullable()->after('provider_webhook_payload');
            }
            if (! Schema::hasColumn('orders', 'provider_webhook_last_error')) {
                $table->text('provider_webhook_last_error')->nullable()->after('provider_webhook_received_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'provider_last_polled_at',
                'provider_status_sync_lock_at',
                'provider_status_sync_lock_owner',
                'provider_webhook_payload',
                'provider_webhook_received_at',
                'provider_webhook_last_error',
            ]);
        });
    }
};
