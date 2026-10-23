<?php

/**
 * Pass 179g — lane 64: §24.17 "Tables that are empty in the demo database" was wrong about five of
 * its eight rows, and nothing checked it.
 *
 * Found by reading the section for a different reason: its `gradescale` row says "No grade-scale row
 * exists", while §24's own entry two hundred lines above says "Rows in the demo database: 4". So the
 * document contradicts itself, and the contradiction is invisible to every instrument in the suite:
 * lane 60 binds §24's per-table row counts (all correct), lane 44 the unique keys, the citation sweeps
 * the pointers — none compares a SECTION MEMBERSHIP with `COUNT(*)`. `information_schema.TABLES
 * .TABLE_ROWS` would not have found it either: it is an InnoDB estimate and reports `clearanceperiods`
 * as 0 while every clearance row joins to it.
 *
 * Measured with COUNT(*) over all 63 base tables on live `ems` (storage/app/scratch
 * /lane64-empty-table-inventory.php): the empty set is cache, cache_locks, creditedsubjects,
 * failed_jobs, job_batches, jobs, model_has_permissions, sessions, shiftingrequests and
 * transferacademicrecords. The section still lists gradescale (4 rows), scholarshiptypes (2),
 * studentscholarships (1), schedulemeetings (18) and settings (3) as empty, and its own
 * `schedulemeetings` cell already said 18 meetings — the prose was re-measured row by row while the
 * membership never was.
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

$counts = [];
foreach (DB::select("SELECT TABLE_NAME n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'") as $r) {
    $counts[$r->n] = (int) DB::select("SELECT COUNT(*) c FROM `{$r->n}`")[0]->c;
}
$guard(count($counts) === 63, 'all 63 base tables counted (read '.count($counts).')');
foreach (['gradescale' => 4, 'scholarshiptypes' => 2, 'studentscholarships' => 1, 'schedulemeetings' => 18, 'settings' => 3] as $t => $want) {
    $guard(($counts[$t] ?? -1) === $want, $t.' answers '.$want.' rows today (read '.($counts[$t] ?? -1).')');
}
foreach (['creditedsubjects', 'model_has_permissions', 'shiftingrequests', 'transferacademicrecords'] as $t) {
    $guard(($counts[$t] ?? -1) === 0, $t.' really is empty (read '.($counts[$t] ?? -1).')');
}
$empty = array_keys(array_filter($counts, fn (int $c): bool => $c === 0));
sort($empty);
$guard(count($empty) === 10, 'ten base tables are empty (read '.count($empty).')');

$asOf = '2026-10-23';
$emptyList = implode(', ', $empty);

$textEdits = [
    [
        'An empty table is a feature the schema supports that the demo data does not exercise, so no walkthrough in this document can claim to have tested it. Each is stated with what would populate it; the reference-data items are carried into §21 and §28.',
        'An empty table is a feature the schema supports that the demo data does not exercise, so no walkthrough '
            .'in this document can claim to have tested it. Counted, not remembered: on '.$asOf.' the base tables '
            .'whose COUNT(*) is 0 are '.$emptyList.' — the last six of them framework tables that §24.18 treats as '
            .'infrastructure, so the domain set this section is about is creditedsubjects, model_has_permissions, '
            .'shiftingrequests and transferacademicrecords. Five rows this section carried on 2026-10-04 were stale '
            .'by the time it was next read: gradescale, scholarshiptypes, studentscholarships, schedulemeetings and '
            .'settings all hold rows on live ems today, and each is rewritten below to say what the demo dataset '
            .'does not exercise rather than that the table is empty. Membership is now re-measured by '
            .'storage/app/scratch/lane64-empty-table-inventory.php, which counts every base table — not '
            .'information_schema.TABLES.TABLE_ROWS, an InnoDB estimate that reports clearanceperiods as 0 while '
            .'every clearance row joins to it.',
    ],
];

$cellEdits = [
    ['Registrar defines the scale (Admin', 'No grade-scale row exists',
        'Not empty, and this row contradicted §24 four lines above: gradescale holds 4 rows on live ems, seeded as '
            .'PROVISIONAL bands by the dev seeder ( database/seeders/DevReferenceDataSeeder.php:770-773 ). What the '
            .'demo dataset does not exercise is a scale the '
            .'Registrar has ruled: every band still reads “To be confirmed by the Registrar”, and a fresh install '
            .'that runs only the starter seeder gets no scale at all, so EvaluationController’s 3.00 '
            .'fallback (§21.5) is still the live behaviour there.'],
    ['Admin → Reference Data → Scholarship Types', 'Nothing to grant',
        'Two types exist on live ems, both seeded and both still labelled provisional, so the desk can grant a '
            .'discount; what the demo data does not exercise is the full award catalogue §21.2 describes. The claim '
            .'that nothing can be granted — and the studentscholarships row below that rested on it — were both '
            .'written before the dev seeder began creating these two rows.'],
    ['Scholarship desk grants one to an assessed student', 'Follows from',
        'Not empty either: one scholarship row stands on live ems, so §3 and §17 have an award to walk. What stays '
            .'unexercised is a REVOKED or EXPIRED grant on the demo dataset — the withdraw path exists '
            .'( AssessmentController::withdrawScholarship ) and no demo row has taken it.'],
    ['Blocking schedules a class meeting', 'Blocking holds 9 schedules and 18 meetings',
        'The row was already contradicting its own heading when it was written — this table holds 18 meetings on '
            .'live ems, which the cell says — and it stayed listed as empty because the section was corrected row by '
            .'row instead of re-counted. Meetings exist; what the demo data does not exercise is a COLLIDING pair, '.
            'since the record-state audit reports 0 (lane 55).'],
    ['Admin defaults are read from code instead', 'Admin defaults are read from code instead',
        'Settings is not empty: three rows stand on live ems, and they are the letterhead (P-7 closed 2026-10-21), '
            .'so §25.5’s print path reads the table through the provider that hydrates config(‘settings.*’) at boot. '
            .'What stays true is the narrower point — admin defaults for everything ELSE are still read from code, '
            .'because only these three keys have rows and only the print templates read them.'],
];

docxApply($src, $root.'/storage/app/scratch/pass179g-backup.docx', $textEdits, $cellEdits, $apply);
