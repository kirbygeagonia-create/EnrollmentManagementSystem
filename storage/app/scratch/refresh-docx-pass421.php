<?php

/**
 * Pass 421 — re-take the lane-49 blind-spot figure §28.3 carries for it.
 *
 * `Rooms.jsx` gained twelve lines, and one of the two figures `lane59-instrument-counts.php` binds is a count of JSX
 * lines that checker cannot judge, so a batch that touched no money cell still moved a documented figure — the case
 * this repository has met three times before (the batch that added a sort block, the batch that added a caption, the
 * batch that added an import). The value is therefore read out of `lane49-zero-shown-as-absence.php` at write time,
 * never typed: a figure copied from the last run is the claim that rotted.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass421.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

// ---------------------------------------------------------- stage 0: ask the instrument what it prints today
$out = [];
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane49-zero-shown-as-absence.php').' 2>&1', $out, $code);
$print = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out));
if ($code !== 0) {
    $fail("lane49 exited {$code}; its figures cannot be read from a run that did not complete.");
}
if (preg_match('/Not a money\/percent figure the guard itself prints: (\d+)/', $print, $m) !== 1) {
    $fail('lane49 printed no "Not a money/percent figure" line — the reader is blind, not the document clean.');
}
$operandLines = (int) $m[1];
if (preg_match('/Not read \(a dash on the line, no recoverable operand\): (\d+)/', $print, $m2) !== 1) {
    $fail('lane49 printed no "Not read" line, so the companion figure cannot be verified before it is left standing.');
}
$unread = (int) $m2[1];
printf("lane49 today: %d lines whose tested operand is not a money/percent figure, %d guards not read.\n", $operandLines, $unread);

$from = '368 lines where the tested operand is a name, code, date or error message';
$to = $operandLines.' lines where the tested operand is a name, code, date or error message';
if ($from === $to) {
    $fail('the document already carries the figure lane49 prints — this pass has nothing to re-take.');
}
// The 21 beside it is a claim too: assert it agrees before leaving it alone, or this pass re-takes one half of a pair.
if ($unread !== 21) {
    $fail("the sentence's other figure is 21 and lane49 answers {$unread}; re-take both in one pass, do not leave a half-moved pair.");
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open for reading.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$hits = substr_count($xml, $from);
if ($hits !== 1) {
    $fail("the needle occurred {$hits} time(s) in document.xml, wanted exactly 1.");
}
if (substr_count($xml, $to) !== 0) {
    $fail('the replacement is already in the document — this pass has run.');
}

printf("%s\n", $apply ? 'applying' : 'DRY RUN');
docxApply($docx, $root.'/storage/app/scratch/pass421-'.($apply ? 'backup-' : 'dryrun-').date('Ymd-His').'.docx', [[$from, $to]], [], $apply);
if (! $apply) {
    exit(0);
}

// ---------------------------------------------------------- read-back, by the instrument that owns the figure
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/dump-docx-flat.php').' 2>&1');
$check = [];
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane59-collect-readings.php').' 2>&1', $check, $collectCode);
$check2 = [];
exec('"'.PHP_BINARY.'" '.escapeshellarg($root.'/storage/app/scratch/lane59-instrument-counts.php')
    ." --readings=storage/app/scratch/lane59-readings.json 2>&1", $check2, $code2);
$res = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $check2));
if ($collectCode !== 0) {
    $fail('the reading collector refused, so the re-take is unverified.');
}
if ($code2 !== 0 || ! str_contains($res, '0 stale and 0 unbound')) {
    $fail("lane59 still does not read clean after the write (exit {$code2}):\n".mb_substr($res, -900));
}
if (! str_contains($res, 'lane49-zero-shown-as-absence')) {
    $fail('lane59 judged no lane49 figure at all — a clean verdict over an unread pair.');
}

echo "written; the figure came from lane49's own print and lane59 --readings now reads 0 stale and 0 unbound.\n";
echo "Now run sweep-docx-citations, lane37, verify-docx, docx-ooxml-check and the Word open test.\n";
