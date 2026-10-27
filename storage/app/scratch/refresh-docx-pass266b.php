<?php

/**
 * The same row's second invented pointer: `G-10 (§6.4)` means the DEVELOPMENT PROMPT's §6.4, but the
 * authority document has a §6.4 of its own, so `lane37` resolved it and reported nothing.
 *
 * A numbering collision is worse than a missing section, because the checker stays quiet: prompt §6.4 is the
 * return mechanism's requirement and document §6.4 is something else entirely. The row now says
 * `prompt §6.4`, which is how the document already refers into the prompt elsewhere, and the sweep was
 * taught to honour that qualifier (its other-document cue list gained `prompt`).
 *
 * Usage: php storage/app/scratch/refresh-docx-pass266b.php [--apply]
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass266b-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the document for probing.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$needle = 'G-10 (§6.4)';
$found = substr_count($xml, $needle);

if ($found !== 1) {
    fwrite(STDERR, "REFUSED: the needle '{$needle}' occurs {$found} time(s); the run split decides whether this is a text edit at all.\n");

    exit(1);
}

printf("read %-16s occurs 1\n", $needle);

docxApply($docx, $backup, [[$needle, 'G-10 (prompt §6.4)', null, 1]], [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach (['G-10 (§6.4)' => 0, 'G-10 (prompt §6.4)' => 1] as $probe => $expected) {
    $seen = substr_count($after, $probe);

    if ($seen !== $expected) {
        fwrite(STDERR, "REFUSED (post-write): '{$probe}' occurs {$seen}, expected {$expected}.\n");

        exit(1);
    }

    printf("after %-24s %d\n", $probe, $seen);
}

echo "\npass 266b applied: the record row's §-pointers now name the prompt where the prompt is meant.\n";
