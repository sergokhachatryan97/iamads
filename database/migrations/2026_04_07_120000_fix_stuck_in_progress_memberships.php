<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix telegram_order_memberships stuck as in_progress where task is already done
        DB::statement("
            UPDATE telegram_order_memberships m
            JOIN telegram_tasks t ON t.order_id = m.order_id
                AND t.link_hash = m.link_hash
                AND JSON_UNQUOTE(JSON_EXTRACT(t.payload, '$.account_phone')) = m.account_phone
            SET m.state = 'subscribed',
                m.subscribed_at = t.updated_at,
                m.last_error = NULL
            WHERE m.state = 'in_progress'
              AND t.status = 'done'
        ");

        // Fix telegram_account_link_states stuck as in_progress where task is already done
        DB::statement("
            UPDATE telegram_account_link_states als
            JOIN telegram_tasks t ON t.link_hash = als.link_hash
                AND als.last_task_id = t.id
            SET als.state = 'subscribed',
                als.last_error = NULL
            WHERE als.state = 'in_progress'
              AND t.status = 'done'
        ");
    }

    public function down(): void
    {
        // Not reversible — data was already in an incorrect state
    }
};
