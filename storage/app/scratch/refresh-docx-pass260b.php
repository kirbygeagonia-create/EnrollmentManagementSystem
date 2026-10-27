<?php

/**
 * §24.19's two aggregate cells, now that the dictionary actually holds the fifteenth table's columns.
 *
 * Pass 259 deliberately left these two standing: "420 columns documented for 55 domain tables" and "54
 * Eloquent models for 55 domain tables" are claims about the dictionary's own contents, and they became
 * true only when pass 260 inserted the `enrollmentreturns` entry. Both new figures are read from the same
 * surfaces the cells name — the server's domain-column count, and a count of the model classes on disk —
 * rather than derived by arithmetic from the old number.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass260b.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass260b-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$readingsPath = $root.'/storage/app/scratch/lane60-readings.json';
$readings = is_file($readingsPath) ? json_decode((string) file_get_contents($readingsPath), true) : null;

if (! is_array($readings)) {
    fwrite(STDERR, "REFUSED: lane60's readings are missing; this pass types no figure it has not read off the server.\n");

    exit(1);
}

if (($readings['domainColumns'] ?? null) !== 435) {
    fwrite(STDERR, 'REFUSED: the reading for domainColumns answers '.var_export($readings['domainColumns'] ?? null, true)." — not 435.\n");

    exit(1);
}

// The model count is the second surface: §24's row counts Eloquent model CLASSES, so count the classes in
// app/Models rather than trusting the server's table count.
$models = glob($root.'/app/Models/*.php') ?: [];

if (count($models) !== 55) {
    fwrite(STDERR, 'REFUSED: app/Models holds '.count($models).' classes, not the 55 this cell is about to state.\n');

    exit(1);
}

printf("read %-24s %d\nread %-24s %d\n", 'domainColumns', $readings['domainColumns'], 'app/Models classes', count($models));

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (! str_contains($xml, 'enrollmentreturns — 15 columns')) {
    fwrite(STDERR, "REFUSED: the enrollmentreturns entry is not in the document yet — pass 260 must run first, or these cells would state a dictionary that does not exist.\n");

    exit(1);
}

$edits = [
    ['420 columns documented for 55 domain tables', '435 columns documented for 56 domain tables', null, 1],
    ['54 Eloquent models for 55 domain tables', '55 Eloquent models for 56 domain tables', null, 1],
];

echo "\n";

docxApply($docx, $backup, $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([
    '420 columns documented for 55 domain tables' => 0,
    '54 Eloquent models for 55 domain tables' => 0,
    '435 columns documented for 56 domain tables' => 1,
    '55 Eloquent models for 56 domain tables' => 1,
] as $needle => $expected) {
    $seen = substr_count($after, $needle);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$needle}' occurs {$seen} time(s), expected {$expected}.\n");

        exit(1);
    }

    printf("after %-46s %d as intended\n", $needle, $seen);
}

echo "\npass 260b applied: §24.19's column and model aggregates answer the dictionary as it now stands.\n";
