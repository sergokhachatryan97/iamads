<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('referral_code', 32)->unique()->nullable()->after('api_last_used_at');
            $table->foreignId('referred_by')->nullable()->after('referral_code')
                ->constrained('clients')->nullOnDelete();
        });

        // Seed the referral bonus percentage setting
        \App\Models\UiText::updateOrCreate(
            ['key' => 'referral_bonus_percent'],
            ['value' => '1', 'is_active' => true]
        );
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['referred_by']);
            $table->dropColumn(['referral_code', 'referred_by']);
        });

        \App\Models\UiText::where('key', 'referral_bonus_percent')->delete();
    }
};
