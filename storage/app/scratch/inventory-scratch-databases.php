<?php

/**
 * Scratch-database inventory, read-only.
 *
 * The ruling was "drop the scratch ones, keep ems", but the offer that came with it promised to show what is
 * inside each before anything is dropped — a schema can be named `ems_fresh` and still hold the one row
 * somebody needed. So this counts, per schema, every table and the rows in the tables that carry real records,
 * plus the newest stamp in each, because "nothing unique here" has to mean "nothing that is not demo residue",
 * not "it is small". It writes nothing and connects with no session-zone forcing, which is also what mysqldump
 * and phpMyAdmin see.
 *
 * usage: php storage/app/scratch/inventory-scratch-databases.php
 */

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$keep = ['information_schema', 'mysql', 'performance_schema', 'sys', 'ems'];

// The tables a row would be missed in. Everything else in these schemas is reference data a seeder recreates.
$real = ['students', 'enrollments', 'staffusers', 'workflowsteps', 'auditlogs', 'enrollmentworkflow',
    'clinicrecords', 'idrequests', 'payments', 'charges', 'admissions', 'studentassessments',
    'studentclearances', 'shiftingrequests', 'schedulemeetings', 'notifications'];

$schemas = array_column(DB::select('SELECT schema_name AS s FROM information_schema.schemata ORDER BY s'), 's');

$rows = [];
foreach ($schemas as $s) {
    if (in_array($s, $keep, true)) {
        continue;
    }
    $tables = array_column(
        DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ? AND table_type = \'BASE TABLE\'', [$s]), 't');
    $present = array_values(array_intersect($real, $tables));
    $detail = [];
    $newest = null;
    $total = 0;
    foreach ($present as $t) {
        $q = DB::selectOne('SELECT COUNT(*) AS c FROM `'.str_replace('`', '``', $s).'`.`'.str_replace('`', '``', $t).'`');
        $c = (int) $q->c;
        $total += $c;
        if ($c === 0) {
            continue;
        }
        $detail[] = $t.'='.$c;
        // The newest stamp in the table, from whichever naming that table actually carries.
        $cols = array_column(DB::select(
            'SELECT column_name AS c FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name IN (\'created_at\',\'createdAt\',\'changedAt\',\'signedDate\',\'receivedDate\',\'date\')',
            [$s, $t]), 'c');
        foreach ($cols as $col) {
            $m = DB::selectOne('SELECT MAX(`'.str_replace('`', '``', $col).'`) AS m FROM `'.str_replace('`', '``', $s).'`.`'.str_replace('`', '``', $t).'`');
            if ($m !== null && $m->m !== null && (string) $m->m > (string) $newest) {
                $newest = (string) $m->m;
            }
        }
    }
    $rows[] = [$s, count($tables), $total, $detail === [] ? '—' : implode(', ', $detail), $newest ?? 'no dated rows'];
}

printf("%-22s %7s %8s  %-58s %s\n", 'SCHEMA', 'tables', 'rows', 'real-record tables', 'newest stamp');
foreach ($rows as [$s, $nt, $total, $detail, $newest]) {
    printf("%-22s %7d %8d  %-58s %s\n", $s, $nt, $total,
        strlen($detail) > 55 ? substr($detail, 0, 52).'…' : $detail, $newest);
}

printf("\nkept, untouched: ems (%d students, %d enrollments, %d audit rows)\n",
    DB::selectOne('SELECT COUNT(*) c FROM ems.students')->c,
    DB::selectOne('SELECT COUNT(*) c FROM ems.enrollments')->c,
    DB::selectOne('SELECT COUNT(*) c FROM ems.auditlogs')->c);
echo "\nNOTHING DROPPED — this tool only counts.\n";
