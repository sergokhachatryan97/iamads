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
        Schema::create('account_profile_seeds', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique()->index(); // normalized lowercase, without '@'
            $table->string('display_name')->nullable();
            $table->text('bio')->nullable();
            $table->text('profile_photo_url')->nullable();
            $table->text('story_url')->nullable();
            $table->text('profile_photo_local_path')->nullable();
            $table->text('story_local_path')->nullable();
            $table->string('profile_photo_mime')->nullable();
            $table->string('story_mime')->nullable();
            $table->string('status')->nullable()->index(); // ready|needs_download|failed
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_profile_seeds');
    }
};
