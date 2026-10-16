<?php

/**
 * Pass 17 — the per-table "Rows in the demo database" figures, re-measured against the live
 * install rather than recalled.
 *
 * The edits are generated: the dictionary's own blocks are parsed out of the flat text dump,
 * each table's row count is read from ems today, and only the figures that moved are queued.
 * The needle ("Rows in the demo database: 16.") repeats verbatim across the chapter, so each
 * edit is resolved positionally after that table's model sentence — which is unique.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$flat = file(dirname(__DIR__, 3).'/storage/app/scratch/docx-flat.txt', FILE_IGNORE_NEW_LINES);

$blocks = [];
$table = null;
foreach ($flat as $line) {
    if (preg_match('/^P \d+: ([a-z_]+) — \d+ columns/', $line, $m)) {
        $table = $m[1];

        continue;
    }
    if ($table !== null && preg_match('/Model App\\\\Models\\\\([A-Za-z]+)/', $line, $m)) {
        $class = $m[1];
        if (preg_match('/Rows in the demo database: (\d+)\./', $line, $c)) {
            $blocks[] = ['table' => $table, 'class' => $class, 'doc' => (int) $c[1]];
        }
        $table = null;
    }
}

require dirname(__DIR__, 3).'/vendor/autoload.php';
$appLaravel = require dirname(__DIR__, 3).'/bootstrap/app.php';
$appLaravel->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$existing = collect(DB::select("select table_name t from information_schema.tables where table_schema='ems'"))
    ->pluck('t')->map(fn ($t) => strtolower($t))->all();

$textEdits = [];
$moved = 0;
foreach ($blocks as $b) {
    if (! in_array($b['table'], $existing, true)) {
        echo "skip   {$b['table']} — no such table live\n";

        continue;
    }
    $live = (int) DB::table($b['table'])->count();
    if ($live === $b['doc']) {
        continue;
    }
    $moved++;
    $textEdits[] = [
        'Rows in the demo database: '.$b['doc'].'.',
        'Rows in the demo database: '.$live.'.',
        'app/Models/'.$b['class'].'.php',
    ];
}

$cellEdits = [
    ['at the revision date', 'The count in the seeded ems database',
        'The count in the seeded ems database at the revision date — re-read table by table against ems on 2026-10-04, which moved '.$moved.' of the '.count($blocks).' figures this chapter carries. It records whether the table is exercised at all; it is not a production figure, and a table that reads 0 is not necessarily broken (auditlogs and settings are written by paths this tool does not exercise).'],
];

echo "generated {$moved} row-count corrections from ".count($blocks)." documented tables\n";

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass17.docx', $textEdits, $cellEdits, $apply);
