<?php

use App\Models\TelegramAccount;
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
        Schema::create('telegram_accounts', function (Blueprint $table) {
            $table->id();

            // ---------- Identity / Auth ----------
            $table->string('phone', 32)->unique();              // +374...
            $table->string('name')->nullable();        // store encrypted via model cast if possible

            // Session storage (choose one or both depending on your architecture)
            $table->enum('session_storage', ['file', 'string', 'db'])->default('file');
            $table->string('session_path')->nullable();         // e.g. storage/app/mtproto/acc_1.session
            $table->longText('session_string')->nullable();     // encrypt this (recommended)
            $table->unsignedSmallInteger('dc_id')->nullable();
            $table->json('proxy')->nullable();                  // {type,host,port,user,pass}

            // ---------- State / Control ----------
            $table->boolean('is_active')->default(true)->index();
            $table->string('status', 32)->default('ready')->index(); // ready|needs_login|banned|flood|error|2fa_required
            $table->timestamp('disabled_until')->nullable()->index();

            $table->timestamp('last_used_at')->nullable()->index();
            $table->timestamp('last_ok_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->string('last_error', 512)->nullable();
            $table->unsignedInteger('fail_count')->default(0);

            // ---------- Usage / Limits ----------
            $table->unsignedInteger('subscription_count')->default(0)->index();
            $table->unsignedInteger('max_subscriptions')->default(400)->index(); // instead of hardcode <400
            $table->unsignedInteger('weight')->default(1);       // optional: weighted selection
            $table->json('tags')->nullable();                    // optional: ["fast","stable"]

            $table->string('onboarding_status', 20)->default('new');
            $table->string('onboarding_step', 50)->nullable();
            $table->text('onboarding_last_error')->nullable();
            $table->string('onboarding_last_task_id', 255)->nullable();
            $table->text('twofa_password_encrypted')->nullable();
            $table->string('desired_profile_name', 255)->nullable();
            $table->string('onboarding_request_seed', 36)->nullable();
            $table->unique('desired_profile_name');
            $table->string('desired_profile_name_norm', 255)->nullable();

            $table->boolean('should_hide_after_name_change')->default(false)->index();
            $table->timestamp('profile_name_changed_at')->nullable()->index();
            $table->boolean('is_visible')->default(true)->index();


            $table->timestamps();

            // Helpful compound indexes
            $table->index(['is_active', 'status']);
            $table->index(['is_active', 'subscription_count']);

        });

        TelegramAccount::whereNotNull('desired_profile_name')->chunkById(100, function ($accounts) {
            foreach ($accounts as $account) {
                $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $account->desired_profile_name)));
                $account->update(['desired_profile_name_norm' => $normalized ?: null]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_accounts');
    }
};

