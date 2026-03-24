<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('star_rating')->nullable()->after('comment_text');
        });

        // Backfill from provider_payload for existing orders
        $orders = DB::table('orders')->whereNotNull('provider_payload')->get(['id', 'provider_payload']);
        foreach ($orders as $order) {
            $payload = json_decode($order->provider_payload, true);
            if (is_array($payload) && isset($payload['star_rating'])) {
                $rating = (int) $payload['star_rating'];
                if ($rating >= 1 && $rating <= 5) {
                    DB::table('orders')->where('id', $order->id)->update(['star_rating' => $rating]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('star_rating');
        });
    }
};
