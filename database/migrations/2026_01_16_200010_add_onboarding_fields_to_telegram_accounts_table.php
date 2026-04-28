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
//        Schema::table('telegram_accounts', function (Blueprint $table) {
//            $table->string('onboarding_status', 20)->default('new')->after('phone_number')->index();
//            $table->string('onboarding_step', 50)->nullable()->after('onboarding_status')->index();
//            $table->text('onboarding_last_error')->nullable()->after('onboarding_step');
//            $table->string('onboarding_last_task_id', 255)->nullable()->after('onboarding_last_error')->index();
//            $table->text('twofa_password_encrypted')->nullable()->after('onboarding_last_task_id');
//            $table->string('desired_profile_name', 255)->nullable()->after('twofa_password_encrypted');
//            $table->string('onboarding_request_seed', 36)->nullable()->after('desired_profile_name')->index(); // UUID
//        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
//        Schema::table('telegram_accounts', function (Blueprint $table) {
//            $table->dropColumn([
//                'onboarding_status',
//                'onboarding_step',
//                'onboarding_last_error',
//                'onboarding_last_task_id',
//                'twofa_password_encrypted',
//                'desired_profile_name',
//                'onboarding_request_seed',
//            ]);
//        });
    }
};
