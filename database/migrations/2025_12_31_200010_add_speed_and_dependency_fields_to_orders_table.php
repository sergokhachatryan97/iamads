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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('speed_tier', 20)->nullable()->after('status')->index();
            $table->decimal('speed_multiplier', 5, 2)->default(1.00)->after('speed_tier');
            $table->foreignId('depends_on_order_id')->nullable()->after('speed_multiplier')
                ->constrained('orders')->onDelete('set null')->index();
            $table->string('depends_status', 20)->nullable()->after('depends_on_order_id');
            $table->timestamp('depends_verified_at')->nullable()->after('depends_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['depends_on_order_id']);
            $table->dropIndex(['speed_tier']);
            $table->dropIndex(['depends_on_order_id']);
            $table->dropColumn([
                'speed_tier',
                'speed_multiplier',
                'depends_on_order_id',
                'depends_status',
                'depends_verified_at',
            ]);
        });
    }
};
