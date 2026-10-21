<?php

/**
 * Pass 168 — the document's own census, re-read against the server it describes.
 *
 * Lane 58 bound the suite totals to instrument output and lane 59 bound the counts the tools print. This pass takes the
 * third family: figures stated in prose over the database. Docx pass 28 re-derived the census on 2026-10-05 (62→63 tables,
 * 86→100 foreign keys) and rewrote some of the sentences that state those numbers while leaving others alone, so the
 * document now carries both readings at once: §23.4's own summary line says 26 SET NULL keys two paragraphs above a row
 * that says "These 16 keys are SET NULL on delete", and §24.1's header says 62/54+8 where §23.8's cross-check says 63.
 *
 * Every replacement value is read out of lane60-readings.json, which its own probe wrote from live `ems` — so this pass
 * cannot state a number its instrument did not print. The row-count figures (role assignments, gradescale rows, settings
 * rows) come from the same file and name the surface they were read on.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$readPath = $root.'/storage/app/scratch/lane60-readings.json';
if (! is_file($readPath)) {
    exit("REFUSED: no lane60-readings.json — run lane60-probe-ratios.php --write-readings=… first.\n");
}
$R = json_decode((string) file_get_contents($readPath), true);
if (! is_array($R) || ! isset($R['tables'], $R['columns'], $R['documentHeadings'])) {
    exit("REFUSED: lane60-readings.json does not carry the census block, so nothing here can be trusted.\n");
}
if (($R['database'] ?? '') !== 'ems') {
    exit("REFUSED: these readings were taken on `".$R['database'].'`, not on live `ems`.\n');
}
foreach (['failedReadings'] as $k) {
    if (! empty($R[$k])) {
        exit("REFUSED: the probe reports ".count($R[$k])." reading(s) it could not take ({$k}), so a figure would be restated from an absent measurement.\n");
    }
}
$heads = $R['documentHeadings'];
if ($heads['differ'] !== 0 || $heads['read'] !== $heads['agree']) {
    exit("REFUSED: {$heads['differ']} of {$heads['read']} §24 column headings disagree with the server; correct the dictionary before its aggregates.\n");
}

$day = (string) $R['schoolDay'];

// The settings claim is only false if the table really is written by a seeder. Check that before rewriting the sentence.
$settingsSeeder = is_file($root.'/database/seeders/SettingsSeeder.php');

$textEdits = [
    // §24's summary line: every one of its four figures is a 2026-10-04 reading, and its "indexes" was never an index count —
    // 175 is what information_schema.TABLE_CONSTRAINTS holds, so the sentence gets both numbers and the right nouns.
    [
        'Counts: 62 tables (54 EMS domain + 8 framework), 418 columns, 86 foreign-key constraints, 175 indexes.',
        sprintf('Counts: %d tables (%d EMS domain + %d framework), %d columns, %d foreign-key constraints, %d indexes, and %d table constraints in all.',
            $R['tables'], $R['domainTables'], $R['frameworkTables'], $R['columns'], $R['foreignKeys'], $R['indexes'], $R['constraints']),
    ],
    ['409 columns documented for 55 domain tables',
        sprintf('%d columns documented for %d domain tables', $R['documentHeadings']['domainStatedColumns'], $R['domainTables'])],
    ['holds 62 tables in all, of which 8 are framework tables',
        sprintf('holds %d tables in all, of which %d are framework tables', $R['tables'], $R['frameworkTables'])],
    ['54 EMS domain tables plus 8 Laravel framework tables at that reading, 55 and 8 today',
        sprintf('%d EMS domain tables plus %d Laravel framework tables at that reading, and re-read on %s as %d domain plus %d framework',
            54, 8, $day, $R['domainTables'], $R['frameworkTables'])],
    ['The live database holds 62 tables: 54 EMS-domain tables and 8 Laravel framework tables.',
        sprintf('The live database holds %d tables: %d EMS-domain tables and %d Laravel framework tables.',
            $R['tables'], $R['domainTables'], $R['frameworkTables'])],
    ['given any row in any of the 54 tables', 'given any row in any of the '.$R['domainTables'].' tables'],
    ['check any one of the 54 against the sentence', 'check any one of the '.$R['domainTables'].' against the sentence'],
    // The run boundary falls after "These 16", so the needle stops there: the rest of the sentence lives in a code run for
    // SET NULL and cannot travel through one text edit with the number.
    ['These 16', 'These '.$R['deleteRules']['setNull']],
    ['54 domain + 8 framework, and every column, key and index quoted',
        sprintf('%d domain + %d framework, and every column, key and index quoted', $R['domainTables'], $R['frameworkTables'])],
    ['21 assignments over 17 roles', sprintf('%d assignments over %d roles', $R['staffRoleAssignments'], $R['roles'])],
    ['has 3 rows and a 3.00 fallback', 'has '.$R['rows']['gradescaleRows'].' rows and a 3.00 fallback'],
    ['holds 0 rows in the demo database',
        sprintf('holds %d rows on the live database, written by SettingsSeeder', $R['rows']['settingsRows'])],
    // ROW 2712's explanation cell. The needle stops at the figures because "SHOW TABLES" is its own code run, so the longer
    // sentence is not a substring of the XML even though the flat dump prints it as one.
    ['54 + 8', sprintf('%d + %d', $R['domainTables'], $R['frameworkTables'])],
];

// A refusal is louder than a skipped edit: the rewrite names SettingsSeeder as the writer of those rows, so the file has
// to be in the tree before the sentence blames it.
if (! $settingsSeeder) {
    exit("REFUSED: the rewrite would name SettingsSeeder as the writer of the settings rows, and no such file is in the tree.\n");
}

$cellEdits = [
    ['Domain tables in the live database', '54', (string) $R['domainTables'], 1],
    // Two rows carry this label and both state 62, so both are rewritten: correcting one copy and leaving the other
    // stating the superseded figure is the defect this pass exists to close.
    ['Tables in the live database', '62', (string) $R['tables'], 2],
    ['Reconciled position', '62', (string) $R['tables'], 1],
];

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

printf("readings from %s on %s (school day %s)\n\n", $R['database'], $R['asOf'], $day);
printf("%d text edit(s), %d cell edit(s)\n\n", count($textEdits), count($cellEdits));

foreach ($textEdits as [$old, $new]) {
    $guard(substr_count($xml, $old) === 1,
        'needle once: '.mb_substr($old, 0, 74));
    foreach (['&', '<', '>'] as $c) {
        $guard(! str_contains($new, $c), 'the replacement carries no raw '.$c);
    }
}
foreach ($cellEdits as [$anchor, $prefix, $value, $expect]) {
    $seen = substr_count($xml, $anchor);
    $guard($seen === $expect, sprintf('row anchor seen %d time(s), stated %d: %s', $seen, $expect, $anchor));
    $guard(trim($value) !== '' && preg_match('/^[\d,]+$/', $value) === 1, 'new cell value is a number: '.$value);
}

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nThis pass would write:\n";
    foreach ($textEdits as [$old, $new]) {
        echo "\n  was: ".mb_substr($old, 0, 150)."\n  now: ".mb_substr($new, 0, 150)."\n";
    }
    foreach ($cellEdits as [$anchor, $prefix, $value]) {
        echo "\n  cell of row \"".$anchor.'": '.$prefix.' → '.$value."\n";
    }
    echo "\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass168.docx', $textEdits, $cellEdits, true);
echo "\nPass 168 applied.\n";
