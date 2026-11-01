<?php

/**
 * Docx pass 345 — take the token count out of the day's record and state the property instead.
 *
 * The clause pass 344 wrote says the shift tool "moved 77 citation tokens". That is a reading of one run on one box:
 * the number is what the tool printed for these two files on this day, nothing on the runner can reproduce it, and
 * AGENTS.md has twice recorded a figure about a tool going stale while the tool itself was untouched — lane 58's rule
 * for a revision row is that it names one surface or states no total. The claim worth keeping is the one the sweep
 * can falsify: every pointer into the two edited files was re-resolved by the tool's own byte windows, and the three
 * bare tails it refused to attribute were re-read by hand.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass345.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass345-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$from = 'The shift tool moved 77 citation tokens for the two files that grew by two and three lines, and its three '
    .'deferred bare tails were re-taken by reading';
$to = 'Every citation into those two files was re-resolved by the shift tool’s own byte windows, since they grew by '
    .'two and three lines, and the three bare tails it refused to attribute were re-taken by reading';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$n = substr_count($xml, $from);
printf('anchor occurs %d time(s), want 1 — %s%s', $n, substr($from, 0, 46), PHP_EOL);
if (str_contains($to, '<') || str_contains($to, '>') || preg_match('/\.php:\d/u', $to) === 1) {
    exit("REFUSED: the replacement carries a tag character or an unverified citation.\n");
}
if ($n !== 1) {
    echo substr_count($xml, $to) === 1 ? "ALREADY APPLIED.\n" : "REFUSED — nothing written.\n";

    exit(substr_count($xml, $to) === 1 ? 0 : 1);
}

docxApply($docx, $backup, [[$from, $to, null, 1]], [], $apply);
if (! $apply) {
    echo "DRY RUN — nothing written.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the written docx did not open as a zip.\n");
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$newN = substr_count($after, $to);
$oldN = substr_count($after, $from);
printf('  new form %d (want 1), retired form %d (want 0)%s', $newN, $oldN, PHP_EOL);
if ($newN !== 1 || $oldN !== 0) {
    fwrite(STDERR, "REFUSED: read-back failed; restoring the pass's own backup.\n");
    if (is_file($backup)) {
        copy($backup, $docx);
    }

    exit(1);
}
// The figure itself must be gone from the living text, not merely reworded beside it.
printf('  the retired figure “77 citation” now occurs %d time(s) in document.xml%s', substr_count($after, '77 citation'), PHP_EOL);
echo "written, re-read: the record states what the sweep can falsify, not what one run printed.\n";
