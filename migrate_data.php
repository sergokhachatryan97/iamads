<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['database.connections.old_mysql' => [
    'driver' => 'mysql',
    'host' => env('MYSQL_HOST', '127.0.0.1'),
    'port' => env('MYSQL_PORT', '3306'),
    'database' => env('MYSQL_DATABASE'),
    'username' => env('MYSQL_USERNAME'),
    'password' => env('MYSQL_PASSWORD'),
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
    $commonColumns = array_intersect($pgColumns, $mysqlColumns);

    if (empty($commonColumns)) {
        echo "SKIP $table (no common columns)\n";
        continue;
    }

    $count = DB::connection('old_mysql')->table($table)->count();
    echo "=== $table ($count rows) ===\n";

    if ($count == 0) {
        echo "  SKIP (empty)\n";
        continue;
    }

    DB::table($table)->truncate();

    $chunkSize = 500;
    $imported = 0;
    $colsList = array_values($commonColumns);

    DB::connection('old_mysql')->table($table)->select($colsList)->orderBy(DB::raw('1'))->chunk($chunkSize, function ($rows) use ($table, &$imported) {
        $batch = $rows->map(fn($r) => (array)$r)->toArray();
        try {
            DB::table($table)->insert($batch);
            $imported += count($batch);
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
        if ($imported % 5000 < 500) {
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
