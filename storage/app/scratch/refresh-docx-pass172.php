<?php

/**
 * Pass 172 — §30 was corrected once and still contradicts the sections around it.
 *
 * Every figure here is read from lane60-readings.json or from the instrument that owns it, and the pass
 * refuses if the instrument disagrees:
 *  - §30.1's title says fifty-four and sixty-two; the census row below it says 55 + 8 = 63, and §24.1 (pass 170)
 *    says 50 tables come from this project's own migrations and 5 are published by the Spatie package. The row
 *    still read "54 | 51 created by the August 2026 baseline migration; 3 are the Spatie pivot tables", which is
 *    the partition before the package's own migration was counted where it lives.
 *  - §30.2's row says "8 unique keys, not six" while §30.1's own paragraph eleven rows above says nine, and the
 *    §23 inventory row carries the same eight. lane44-unique-keys.php answers the server: 9 keys across 7 tables,
 *    and §23.6.1's heading sentence reads "Nine keys in seven tables." — which this pass requires it to still say.
 *  - §30.4's framing counts three-correct, two-stale, one-unmaintained; the table it introduces has four rows
 *    that say "Pays" and two that do not.
 *  - §30.4's yearLevel row states the defect G-2 closed on 2026-10-04, and §30.6 names C-11, D-1, X-3 and G-2 as
 *    four open items — four rows whose own verdict cells read Ruled, Closed, Contained and Closed.
 *  - §30.5 calls the settings table empty and says its emptiness is why letterheads print a code default. It
 *    holds three rows; one carries the school's name; the bridge §25.5 now describes is what reaches the paper.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ---------------------------------------------------------------- guards: the instruments, not my memory

$readPath = $root.'/storage/app/scratch/lane60-readings.json';
if (! is_file($readPath)) {
    exit("REFUSED: no lane60-readings.json — run lane60-probe-ratios.php --write-readings=… first.\n");
}
$R = json_decode((string) file_get_contents($readPath), true);
if (($R['database'] ?? '') !== 'ems') {
    exit('REFUSED: readings taken on '.$R['database'].", not on live `ems`.\n");
}
if (! empty($R['failedReadings'])) {
    exit("REFUSED: the probe reports ".count($R['failedReadings'])." reading(s) it could not take.\n");
}
foreach (['tables' => 63, 'frameworkTables' => 8, 'domainTables' => 55,
    'tablesByOwnMigrations' => 50, 'tablesByPackageMigration' => 5,] as $need => $want) {
    if (($R[$need] ?? null) !== $want) {
        printf("REFUSED: the probe answers %s = %s, and this pass writes sentences that assume %d.\n",
            $need, var_export($R[$need] ?? null, true), $want);
        exit(1);
    }
}
if ($R['domainTables'] !== $R['tablesByOwnMigrations'] + $R['tablesByPackageMigration']) {
    printf("REFUSED: %d + %d does not make the %d domain tables the server holds.\n",
        $R['tablesByOwnMigrations'], $R['tablesByPackageMigration'], $R['domainTables']);
    exit(1);
}
$pkgNames = $R['tablesByPackageMigrationNames'] ?? [];
if (count($pkgNames) !== 5) {
    exit("REFUSED: the readings name ".count($pkgNames)." package tables, not 5.\n");
}
sort($pkgNames);
$pkgList = implode(', ', $pkgNames);

$dumpPath = $root.'/storage/app/scratch/docx-flat.txt';
if (! is_file($dumpPath)) {
    exit("REFUSED: no dump to read §23.6.1's own sentence from.\n");
}
$dump = (string) file_get_contents($dumpPath);
if (! preg_match('/Nine keys in seven tables\./', $dump)) {
    exit("REFUSED: §23.6.1's sentence does not read “Nine keys in seven tables.” — re-measure before writing 30.2.\n");
}

// ---------------------------------------------------------------- the edits

$textEdits = [
    [
        'Fifty-four, and the sixty-two tables in the database',
        'Fifty-five, and the sixty-three tables in the database',
    ],
    [
        'Applied to the live database the number is still defensible, but not for the same set of tables the first list contained',
        'Applied to the live database today the same rule yields 55 domain tables, not the 54 this section’s title carried, and not for the same set of tables the first list contained',
    ],
    [
        'The domain tables hold 8 unique keys, not six',
        'The domain tables hold nine unique keys, not six',
    ],
    [
        '8 across the domain tables, listed individually in §23.6.1',
        'nine across the domain tables, listed individually in §23.6.1',
    ],
    [
        'three of them are the rule working correctly — a snapshot, a copy of an outside record, a pointer kept next to a trail — while two are stale-by-construction because nothing maintains them, and one is not maintained at all',
        'four of them are the rule working correctly — a snapshot, a copy of another institution’s record, a pointer kept beside a trail, and a price held at the moment it was charged — while two do not, and the table below says which',
    ],
    [
        'Does not pay. Every enrollment this system creates carries 1 and nothing promotes it (G-2 in 28.3).',
        'Does not pay the way this row first read it. The level is derived at intake by Enrollments::derivedYearLevel() '
            . 'and the Evaluation desk may place a student elsewhere; G-2 closed on 2026-10-04, so what the column '
            . 'duplicates is a decision the desk records, not a fact the schema forgets.',
    ],
    [
        'Its emptiness is the reason letterheads and fees print a code default: the value is looked up in a table nobody has populated. The gap is in the data, not in the template.',
        'Its two blank rows are why a letterhead prints the school’s name and no address: the table is populated — 3 rows, one of them the name — and a provider carries every row into config('
            . '‘settings.*’) at boot, so the gap is in the data the Registrar has not supplied, not in the template and not in the mechanism. §25.5 and P-7 carry the bridge and what still owes a value.',
    ],
    [
        'They are counted inside the 54',
        'They are counted inside the 55',
    ],
];

$cellEdits = [
    [
        'Everything the school’s process is recorded in.',
        '54',
        '55',
        1,
    ],
    [
        'Everything the school’s process is recorded in.',
        'Everything the school’s process is recorded in.',
        'Everything the school’s process is recorded in. 50 are created by this project’s own migrations and 5 are published by the Spatie permission package — '.$pkgList
            .' — which the code reads as domain data because that is where every desk account’s role lives (§22.4). §24.1 carries the same split; the row this one replaces read 51 plus 3, written before the package’s own migration was counted where it lives.',
        1,
    ],
    [
        'Its emptiness is the reason letterheads and fees print a code default',
        'The empty settings table',
        'The settings table, with two rows left blank',
        1,
    ],
];

$paraEdits = [
    [
        'Four open items determine whether this section describes a design or an accident',
        'Nothing in this section waits on an institutional answer, and that is worth stating plainly, because the paragraph this one replaces named four open items. C-11 was ruled on 2026-10-02 and built; D-1 closed under ruling 12 on 2026-10-04; X-3 was contained on 2026-10-01; G-2 closed on 2026-10-04. Each answer sits in its own register row with the evidence the row cites, and lane61-open-claims.php now refuses the document if a sentence elsewhere asks one of them again. So 30.2 and 30.4 are written as observations about the build, not as questions to the school: where an argument below no longer matches the code, the row says what the code does and names the section that carries the correction. The work this document still owes is not institutional either — it is the two blank letterhead rows (P-7), the program and term the enrollment form cannot print (P-2), and the proxy an operator must name before HSTS means anything (G-13).',
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass172-backup.docx', $textEdits, $cellEdits, $apply, $paraEdits);
