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
        Schema::table('services', function (Blueprint $table) {
            $table->string('target_type', 20)->nullable()->after('service_type')->index();
            $table->boolean('speed_limit_enabled')->default(false)->after('template_snapshot');
            $table->decimal('speed_multiplier_fast', 5, 2)->default(1.50)->after('speed_limit_enabled');
            $table->decimal('speed_multiplier_super_fast', 5, 2)->default(2.00)->after('speed_multiplier_fast');
            $table->boolean('requires_subscription')->default(false)->after('speed_multiplier_super_fast');
            $table->string('required_subscription_template_key', 50)->nullable()->after('requires_subscription');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['target_type']);
            $table->dropColumn([
                'target_type',
                'speed_limit_enabled',
                'speed_multiplier_fast',
                'speed_multiplier_super_fast',
                'requires_subscription',
                'required_subscription_template_key',
            ]);
        });
    }
};
