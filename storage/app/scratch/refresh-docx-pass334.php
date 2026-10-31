<?php

/**
 * Docx pass 334 — the G-52 row carried two clauses written from the superseded reading, and pass 333 moved the
 * figures without touching them.
 *
 * The row now states, correctly, that 5 of the 7 detail screens and 3 of the 4 print paths paint the name SUFFIX,
 * because the census prints that split. Two earlier clauses in the SAME row still asserted "the four detail screens
 * that paint one" and "Every one of the 13 desk list sites is now narrowed alongside the four the first reading
 * named". The first is a count the instrument disproved; the second is a count of a population the census no longer
 * holds (it answers no LIST site at all today, because every list site has been narrowed) — and a row that states
 * one number in its new half and another in its old half is the two-answers-for-one-word defect this repository
 * refuses, inside a single register row.
 *
 * Both clauses are re-worded to name the property rather than a digit, which is the honest form: the derived counts
 * live in the sentences pass 333 wrote from the census, and a sentence about how many sites were narrowed is a
 * reading of this box's scratch tools rather than a claim the runner can reproduce.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass334.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass334-backup.docx';

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$textEdits = [
    [
        'Every one of the 13 desk list sites is now narrowed alongside the four the first reading named, each a line-neutral',
        'Every desk list the census counted is now narrowed, alongside the queues the first reading named, each a line-neutral',
    ],
    [
        'so the four detail screens that paint one would have lost',
        'so any screen that paints one would have lost',
    ],
];

$failures = [];
$before = [];
foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    $before[$i] = $n;
    printf('edit %d: %d occurrence(s) — %s%s', $i, $n, substr($from, 0, 58), PHP_EOL);
    if ($n !== 1) {
        $failures[] = "edit {$i} matched {$n} time(s), wanted exactly 1";
    }
    if (preg_match('/\.php:\d/u', $to) === 1) {
        $failures[] = "edit {$i}'s replacement introduces a line citation the pass has not verified";
    }
}
if ($failures !== []) {
    $already = true;
    foreach ($textEdits as [, $to]) {
        if (substr_count($xml, $to) !== 1) {
            $already = false;

            break;
        }
    }
    if ($already) {
        echo "ALREADY APPLIED — the row states one set of figures.\n";

        exit(0);
    }
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");

    exit(1);
}

docxApply($docx, $backup, $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$bad = 0;
foreach ($textEdits as $i => [$from, $to]) {
    $g = substr_count($after, $to);
    $l = substr_count($after, $from);
    printf('  edit %d: new form %d (want 1), old form %d (want %d)%s', $i, $g, $l, $before[$i] - 1, PHP_EOL);
    if ($g !== 1 || $l !== $before[$i] - 1) {
        $bad++;
    }
}
// The row must not keep any other count of screens that paint a suffix, and must keep exactly the derived pair.
foreach (['four detail screens', '13 desk list sites'] as $stale) {
    if (substr_count($after, $stale) !== 0) {
        echo "  the superseded clause '{$stale}' still occurs in the document\n";
        $bad++;
    }
}
if ($bad > 0) {
    fwrite(STDERR, "REFUSED: {$bad} read-back check(s) failed.\n");
    if (is_file($backup) && copy($backup, $docx)) {
        fwrite(STDERR, 'restored '.basename($backup).'; nothing of the bad write is left.'."\n");
    } else {
        fwrite(STDERR, "AND no backup was kept, so the write stands — read the row back before editing anything else.\n");
    }

    exit(1);
}
echo "written, re-read: the G-52 row states one set of figures, and the derived split is the only count of it.\n";
