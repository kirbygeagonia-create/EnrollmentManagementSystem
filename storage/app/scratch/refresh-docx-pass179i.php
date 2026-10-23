<?php

/**
 * Pass 179i — lane 64, part 2: §21.2's "is it seeded, is there a screen" table had never been
 * re-counted, and eight of its rows were wrong on live `ems` today.
 *
 * Found by following one thread: §24.17 listed `gradescale` as empty while §24 said it holds 4 rows.
 * §21.2's coverage table made the same claim about the same table, plus "No CRUD screen" for a table
 * with four routes (routes/web.php:223-226) and a 310-line React page, and it carried the seeded
 * counts of eleven other tables as facts. Every number below was read from the server first:
 *   academicyears 3 · academicunits 6 · academicterms 9 · religions 8 · offices 9 (ids 1-7, 11, 22 —
 *   no id 8, retired by ruling 12) · rooms 3 (101/40, 102/40, 201/35) · feetypes 5 · scholarshiptypes 2 ·
 *   gradescale 4 · admissionrequirements 4 · clearancerequirements 9 · blocks 5 · settings 3.
 * The pattern is that a seeder's *provisional* rows were recorded as absent — scholarshiptypes by
 * D-3's own closure contradicts row 803, settings by P-7's closure contradicts row 809 — and two rows
 * described the pre-ruling-12 office set.
 *
 * DRY RUN unless --apply.
 */

require_once __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$want = ['academicyears' => 3, 'academicunits' => 6, 'academicterms' => 9, 'religions' => 8, 'offices' => 9,
    'rooms' => 3, 'feetypes' => 5, 'scholarshiptypes' => 2, 'gradescale' => 4, 'admissionrequirements' => 4,
    'clearancerequirements' => 9, 'blocks' => 5, 'settings' => 3];
foreach ($want as $t => $n) {
    $read = (int) DB::select("SELECT COUNT(*) c FROM `{$t}`")[0]->c;
    $guard($read === $n, $t.' answers '.$n.' (read '.$read.')');
}
$ids = DB::select('SELECT GROUP_CONCAT(officeId ORDER BY officeId) g FROM offices')[0]->g;
$guard($ids === '1,2,3,4,5,6,7,11,22', 'the office ids on the server are 1-7, 11, 22 (read '.$ids.')');
$routes = (string) file_get_contents($root.'/routes/web.php');
$guard(substr_count($routes, 'reference-data/grade-scale') === 4,
    'grade scale has four CRUD routes, so “No CRUD screen” is false (read '.substr_count($routes, 'reference-data/grade-scale').')');
$seeder = (string) file_get_contents($root.'/database/seeders/DevReferenceDataSeeder.php');
$guard(str_contains($seeder, 'Gradescale::updateOrCreate'), 'and the dev seeder writes its bands');

$asOf = '2026-10-23';

// §21.2's sentence about the six tables is split across runs, so the whole paragraph is rewritten
// through the engine's paragraph kind rather than spliced.
$paraEdits = [
    [
        'have no rows from the seeder',
        'Re-counted on '.$asOf.' after this table was found to disagree with the server: every one of those six claims was '.
            'wrong. scholarshiptypes holds 2 rows, gradescale 4, settings 3, blocks 5, religions 8 and rooms 3 — the first '.
            'three seeded (the dev seeder’s provisional bands and types, and the three letterhead rows P-7 closed), blocks '.
            'written by the demo pipeline rather than by a seeder. A “not seeded” note that survives because what is seeded '.
            'is provisional is how this section came to contradict §24, §25.5 and D-3’s own closure at the same time.',
    ],
];

$cellEdits = [
    // The religions, offices and settings rows are addressed by a phrase inside their own row, because
    // the table-name cell is a run the rest of the document also uses.
    ['>One row<', 'One row',
        'Eight rows, one of them Roman Catholic, created by StarterReferenceDataSeeder with firstOrCreate keyed on '.
            'religionName — so the ids float with the table’s AUTO_INCREMENT counter and a test must pin one rather than '.
            'assume 1 (the trap §22.6 records)'],
    ['Creating an office row', 'Yes, ids 1-8',
        'Yes, nine rows — ids 1-7, 11 and 22. Id 8 “Clearance” was retired in data under ruling 12, so the row this cell '.
            'described no longer exists (D-1, §28.5)'],
    ['is held by SysAdmin only', 'No',
        'Three rows: the letterhead keys schoolName, schoolAddress and schoolPhone, seeded since P-7 closed on '.
            '2026-10-21 and read by every print template through the provider §25.5 describes'],
    ['Capacity is enforced when assigning students', 'One row — Room 101',
        'Three rows: Room 101 (40), Room 102 (40), Room 201 (35)'],
    ['Tuition ₱1,250/unit', 'Yes, 4 (',
        'Yes, 5 — the fifth is the Clearance Slip Replacement fee ruling 8 made the slip’s own amount'],
    ['Assessment can record a scholarship only against an existing type row', 'No — not seeded',
        'Two provisional types are seeded, and D-3 closed on exactly that; on a fresh install the contract the note describes — '.
            'a grant needs a type row — is satisfied by those two rather than by nothing'],
    ['the prerequisite / retention ceiling silently becomes', 'No CRUD screen',
        'Yes — four routes, GET, POST, PATCH and DELETE at routes/web.php:223-226, gated on refdata.gradeScale.manage, with '.
            'Admin/ReferenceData/GradeScales.jsx behind them'],
    ['the prerequisite / retention ceiling silently becomes', 'No — not seeded',
        'Four bands, seeded by DevReferenceDataSeeder:770-773 and each still worded “To be confirmed by the Registrar” (the '
            .'scale is the school’s to rule, and §24.17 carried this table as empty until 2026-10-23)'],
    ['create and delete only (no update route)', 'Yes — one row per seeded office id',
        'Yes — nine rows, one per office the seeder seats; office 8’s line was retired with the office under ruling 12'],
    ['See §21.3', 'No',
        'Not by a seeder — the demo pipeline creates 5 blocks, which is why §3’s walkthrough has a timetable to read'],
];

docxApply($src, $root.'/storage/app/scratch/pass179i-backup.docx', [], $cellEdits, $apply, $paraEdits);
