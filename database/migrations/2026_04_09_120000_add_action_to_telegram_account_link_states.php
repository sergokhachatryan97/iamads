<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->string('action', 64)->default('subscribe')->after('link_hash');
        });

        // Drop old unique and create new one with action
        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->dropUnique(['account_phone', 'link_hash']);
            $table->unique(['account_phone', 'link_hash', 'action'], 'tg_link_states_phone_link_action_uq');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_account_link_states', function (Blueprint $table) {
            $table->dropUnique('tg_link_states_phone_link_action_uq');
            $table->unique(['account_phone', 'link_hash']);
            $table->dropColumn('action');
        });
    }
};
