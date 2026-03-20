<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'source')) {
                $table->string('source')->default('web')->nullable()->after('mode');
            }
            if (!Schema::hasColumn('orders', 'external_order_id')) {
                $table->string('external_order_id')->nullable()->after('provider_order_id');
            }
        });

        $indexName = 'orders_client_id_external_order_id_unique';
        if (!$this->indexExists('orders', $indexName)) {
            Schema::table('orders', function (Blueprint $table) use ($indexName) {
                $table->unique(['client_id', 'external_order_id'], $indexName);
            });
        }
    }

    public function down(): void
    {
        $indexName = 'orders_client_id_external_order_id_unique';
        if ($this->indexExists('orders', $indexName)) {
            Schema::table('orders', function (Blueprint $table) use ($indexName) {
                $table->dropUnique($indexName);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'source')) {
                $table->dropColumn('source');
            }
            if (Schema::hasColumn('orders', 'external_order_id')) {
                $table->dropColumn('external_order_id');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                'SELECT name FROM sqlite_master WHERE type = ? AND tbl_name = ? AND name = ?',
                ['index', $table, $indexName]
            );
            return (bool) $row;
        }

        if ($driver === 'mysql') {
            $dbName = DB::getDatabaseName();
            $row = DB::selectOne(
                'SELECT 1 FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?
                 LIMIT 1',
                [$dbName, $table, $indexName]
            );
            return (bool) $row;
        }

        return false;
    }
};
