<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recalculate delivered/remains on orders based on actual subscribed memberships
        DB::statement("
            UPDATE orders o
            JOIN (
                SELECT order_id, COUNT(*) as subscribed_count
                FROM telegram_order_memberships
                WHERE state = 'subscribed'
                GROUP BY order_id
            ) counts ON counts.order_id = o.id
            SET o.delivered = counts.subscribed_count,
                o.remains = GREATEST(0, o.target_quantity - counts.subscribed_count),
                o.status = CASE
                    WHEN counts.subscribed_count >= o.target_quantity THEN 'completed'
                    ELSE o.status
                END,
                o.completed_at = CASE
                    WHEN counts.subscribed_count >= o.target_quantity AND o.completed_at IS NULL THEN NOW()
                    ELSE o.completed_at
                END
            WHERE o.delivered < counts.subscribed_count
        ");
    }

    public function down(): void
    {
        // Not reversible
    }
};
