<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'orders';
        $indexName = 'orders_provider_provider_order_id_unique';

        if ($this->indexExists($table, $indexName)) {
            Log::info("Skip creating index {$indexName}: already exists");
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->unique(['provider', 'provider_order_id'], $indexName);
        });
    }

    public function down(): void
    {
        $table = 'orders';
        $indexName = 'orders_provider_provider_order_id_unique';

        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropUnique($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $indexName]
            );
            return (bool) $row;
        }

        if ($driver === 'mysql') {
            $dbName = DB::getDatabaseName();
            $row = DB::selectOne(
                "SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 LIMIT 1",
                [$dbName, $table, $indexName]
            );
            return (bool) $row;
        }

        // fallback: assume not exists
        return false;
    }
};
