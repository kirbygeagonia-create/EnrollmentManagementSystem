<?php

/**
 * Pass 316: the campus clearance queue joins the wired tables, and two sentences that counted three move with it.
 *
 * G-40's row states the tally, and today's §1.1 row states the same fact in prose. Both were written when three
 * pages called the shared screen module; a register row that stops growing when the thing it counts does is the
 * defect this document names over and over, so both are re-taken in the pass that makes them false.
 *
 * The §1.1 sentence loses its number rather than gaining a newer one: "all three now call one module" was the
 * state at the moment that row recorded, and re-typing it as four would make the row state something that was not
 * true when it was written. What survives is the promise without the count.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass316.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$edits = [
    [
        'on the Admission queue and on the student directory — 33 of the 36 pages',
        'on the Admission queue, on the student directory and on the campus clearance queue — 32 of the 36 pages',
    ],
    [
        'the three wired pages call resources/js/Components/ui/listSort.js',
        'the four wired pages call resources/js/Components/ui/listSort.js',
    ],
    [
        'so all three now call one module',
        'so the wired pages call one module',
    ],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as $i => [$from, $to]) {
    if (($h = substr_count($xml, $from)) !== 1) {
        fwrite(STDERR, "REFUSED: edit {$i} needle occurs {$h}, want 1: [{$from}]\n");

        exit(1);
    }
}

printf("%s: the tally and its two sentences move together\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass316-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass316-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([['33 of the 36 pages', 0], ['32 of the 36 pages', 1], ['the four wired pages call', 1], ['all three now call', 0]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED: one tally, one count of pages, one dated row relieved of a number it can no longer hold.\n";
