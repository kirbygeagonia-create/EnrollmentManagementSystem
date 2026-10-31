<?php
/**
 * Lane 107 diagnostic: is the whole-suite run stalled on the database, or is its output merely buffered?
 *
 * Read-only. It asks MySQL for the non-sleeping threads and for the longest-lock wait, and prints the log file's
 * age, so "no progress" can be told apart from "no new lines written". It never touches a row.
 */
require __DIR__.'/../../../vendor/autoload.php';
use Illuminate\Contracts\Console\Kernel;

$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$log = 'storage/app/scratch/lane107-suite.txt';
$rootDir = dirname(__DIR__, 3);
$age = (time() - (int) filemtime($rootDir.'/'.$log));
printf("log %s: %d bytes, mtime %ds ago, lines %d\n", $log, filesize($rootDir.'/'.$log), $age, count(file($rootDir.'/'.$log)));

try {
    $rows = DB::select('SELECT id, user, db, command, time, state, LEFT(info,70) AS q FROM information_schema.processlist WHERE command <> "Sleep" ORDER BY time DESC LIMIT 10');
    if ($rows === []) {
        echo "no non-sleeping server threads — nothing is executing in MySQL right now\n";
    }
    foreach ($rows as $r) {
        printf("  id %-6s db %-10s cmd %-8s time %4ds state [%s] %s\n", $r->id, (string) $r->db, $r->command, $r->time, (string) $r->state, (string) $r->q);
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'processlist unreadable: '.$e->getMessage()."\n");
    exit(1);
}

$locks = DB::select('SELECT COUNT(*) AS n FROM performance_schema.data_lock_waits');
printf("active lock waits: %d\n", (int) ($locks[0]->n ?? 0));
