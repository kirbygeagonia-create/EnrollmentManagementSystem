<?php

/**
 * Tiny SQL runner for the mutation harnesses: takes one statement per argument, prints each row as
 * `column: value`, and exits non-zero on an error. It boots the real application so the database, host and
 * credentials come from the ordinary env the app already uses — a harness that hardcodes them proves the
 * wrong server.
 *
 * usage: DB_DATABASE=ems_lane36_rehearse php storage/app/scratch/mutate-prune-run-sql.php "SELECT COUNT(*) ..."
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = $argv[1] ?? '';
if ($sql === '') {
    fwrite(STDERR, "usage: php storage/app/scratch/mutate-prune-run-sql.php \"<one statement>\"\n");
    exit(2);
}

try {
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if (preg_match('/^(select|show|describe|explain)\b/i', $stmt)) {
            foreach (DB::select($stmt) as $row) {
                foreach ((array) $row as $k => $v) {
                    echo $k.': '.$v.' ';
                }
                echo "\n";
            }

            continue;
        }
        printf("affected: %d\n", DB::statement($stmt));
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'SQL ERROR: '.$e->getMessage()."\n");
    exit(1);
}

exit(0);
