<?php

/**
 * Docx pass 342 — a class figure inside a dated §1.1 row must carry its own date in the sentence.
 *
 * `lane58-suite-claims.php` reads a class's total from whichever junit is beside it and judged the 2026-10-28
 * revision row's `PrintControllerTest answers 26 tests over 155 assertions` as a PRESENT claim, because its dated-row
 * exemption is per *sentence*: a §1.1 row whose first cell is a bare date excuses nothing unless the sentence itself
 * names the day. This batch added a wire case to that class, so the delivered run answers 27 over 179 — and the row
 * was right about 2026-10-28 while reading as a claim about today.
 *
 * Two repairs were possible and only one is honest. Re-typing 27/179 into that row would state a figure that was not
 * true on the day the row records (lane 60's rule, met twice before: pass 179's 452, pass 244's "seven against the
 * two"). Weakening the checker to exempt a whole dated row would blind it to the living row's class figures, which is
 * the surface the instrument exists for. So the sentence is given the date it was read on and the digits stay put.
 *
 * No new present-tense total is written, because a class's assertion count is a reading of one run and the runner
 * answers its own — lane 58's one-surface rule. Today's work is recorded in the 2026-10-31 row, which states none.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass342.php [--apply]
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$backup = $root.'/storage/app/scratch/pass342-backup.docx';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}
require __DIR__.'/docx-edit-engine.php';

$from = 'PrintControllerTest answers 26 tests over 155 assertions and ReferenceDataCrudTest 13 over 186';
$to = 'PrintControllerTest answered 26 tests over 155 assertions on the run this row records, 2026-10-28, and '
    .'ReferenceDataCrudTest 13 over 186 on that same run';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the docx did not open as a zip.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$hits = substr_count($xml, $from);
printf('anchor occurs %d time(s), want 1 — %s%s', $hits, substr($from, 0, 46), PHP_EOL);
if (preg_match('/\.php:\d/u', $to) === 1) {
    echo "REFUSED: the replacement introduces an unverified line citation.\n";

    exit(1);
}
if ($hits !== 1) {
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

echo "written, re-read: the 2026-10-28 row keeps its own digits and now says which run they came from.\n";
