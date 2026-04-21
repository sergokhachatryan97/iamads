<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 16)->unique()->nullable()->after('email');
        });

        // Generate referral codes for existing staff users
        foreach (\App\Models\User::all() as $user) {
            $user->update(['referral_code' => $this->generateCode()]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });
    }

    private function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (\App\Models\User::where('referral_code', $code)->exists());

        return $code;
    }
};
