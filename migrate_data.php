<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.connections.old_mysql' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'temp_import',
    'username' => 'pgloader',
    'password' => 'pgloader123',
]]);

$tables = DB::connection('old_mysql')->select('SHOW TABLES');

DB::statement('SET session_replication_role = replica;');

foreach ($tables as $tableObj) {
    $table = array_values((array)$tableObj)[0];

    if (!Schema::hasTable($table)) {
        echo "SKIP $table (not in PG)\n";
        continue;
    }

    $pgColumns = Schema::getColumnListing($table);
    $mysqlColumns = DB::connection('old_mysql')->getSchemaBuilder()->getColumnListing($table);
    $commonColumns = array_values(array_intersect($pgColumns, $mysqlColumns));

    if (empty($commonColumns)) {
        echo "SKIP $table (no common columns)\n";
        continue;
    }

    $pgCount = DB::table($table)->count();
    $mysqlCount = DB::connection('old_mysql')->table($table)->count();

    if ($pgCount > 0 && $pgCount >= $mysqlCount * 0.9) {
        echo "SKIP $table (already has $pgCount rows)\n";
        continue;
    }

    echo "=== $table ($mysqlCount rows) ===\n";

    if ($mysqlCount == 0) {
        echo "  SKIP (empty)\n";
        continue;
    }

    DB::table($table)->truncate();

    $chunkSize = 5000;
    $imported = 0;

    DB::connection('old_mysql')->table($table)->select($commonColumns)->orderBy(DB::raw('1'))->chunk($chunkSize, function ($rows) use ($table, &$imported, $chunkSize) {
        $batch = $rows->map(fn($r) => (array)$r)->toArray();
        try {
            // Insert in sub-batches to avoid memory issues
            foreach (array_chunk($batch, 1000) as $subBatch) {
                DB::table($table)->insert($subBatch);
                $imported += count($subBatch);
            }
        } catch (\Exception $e) {
            foreach ($batch as $row) {
                try {
                    DB::table($table)->insert($row);
                    $imported++;
                } catch (\Exception $e2) {
                    // skip bad row
                }
            }
        }
        if ($imported % 50000 < $chunkSize) {
            echo "  ... $imported rows\n";
        }
    });

    echo "  DONE: $imported rows\n";
}

DB::statement('SET session_replication_role = DEFAULT;');

$tables2 = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
foreach ($tables2 as $t) {
    $tn = $t->tablename;
    try {
        $max = DB::table($tn)->max('id');
        if ($max) {
            DB::statement("SELECT setval(pg_get_serial_sequence('$tn', 'id'), $max)");
        }
    } catch (\Exception $e) {}
}

echo "\nALL DONE!\n";
