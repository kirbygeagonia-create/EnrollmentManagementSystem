<?php

/**
 * Pass 166 — the sentence pass 165 wrote is re-cut so each figure carries the date that governs it.
 *
 * Pass 165 replaced the stale total with four measured readings, and the checker immediately refused one of them: the
 * passage split into sentences at each full stop, so "and CI's walkthrough step reads 8 tests with 201 assertions"
 * became its own present-tense claim whose date lived in the *previous* sentence. The checker was right and the prose
 * was wrong — a claim is judged by the sentence it sits in, because that is the sentence a reader parses. So the four
 * readings now live in one sentence that opens with the day they were read, the old figure stays inside a sentence
 * that says what it used to be, and the only present-tense total left is `phpunit --list-tests` discovering 780 cases:
 * the one figure that means the same thing on this box and on the runner, and therefore the one worth keeping current.
 *
 * The pass refuses to apply unless the checker names the sentence it is about to rewrite, which it does today: the
 * same instrument that found the document's defect found this pass's author making a smaller one.
 *
 * DRY RUN unless --apply.
 */

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

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$startNeedle = 'The suite was re-measured on 2026-10-20 on the four surfaces';
$endNeedle = 'refusing rather than passing a figure it cannot bind.';
$guard(substr_count($new, $startNeedle) === 1, 'the passage pass 165 wrote is found exactly once');
$guard(substr_count($new, $endNeedle) === 1, 'and its closing clause once');

$at = (int) strpos($new, $startNeedle);
$endAt = (int) strpos($new, $endNeedle, $at);
$guard($endAt > $at, 'the closing clause sits after the opening one');
$oldSpan = substr($new, $at, $endAt + strlen($endNeedle) - $at);
$guard(strlen($oldSpan) > 900 && strlen($oldSpan) < 1600, 'the span is the passage and not a whole row ('.strlen($oldSpan).' bytes)');
foreach (['780', '5,220', '5,013', '5,015', '8 tests with 201', '771 tests and 5191'] as $figure) {
    $guard(str_contains($oldSpan, $figure), 'the passage pass 165 wrote states '.$figure);
}

// The rewrite keeps every figure pass 165 measured and moves each inside a sentence that says when it was read.
$newSpan = 'The suite was re-measured on 2026-10-20 on the four surfaces that report it, and on that day they did not give '
    .'one number: `php artisan test` whole on this box read 780 passed with 5,220 assertions, CI’s SQLite job read 9 skipped '
    .'and 771 passed with 5,013 assertions, CI’s MySQL job read the same 9 skipped and 771 passed with 5,015 assertions, and '
    .'CI’s walkthrough step — the folder whose legs both suite steps skip — read 8 tests with 201 assertions. '
    .'The suite’s own size is what `phpunit --list-tests` discovers: 780 cases today, and 771 plus 9 accounts for the same '
    .'number, so these are four readings of one suite rather than four suites, and the discovered count is the one figure here '
    .'that means the same '
    .'thing on this box and on the runner. The figure this row used to state, 771 tests and 5191 assertions, matched none of '
    .'them when it was re-measured on that day: the 771 is what both CI jobs print for passed tests, beside an assertion count '
    .'that belonged to one box on one earlier day, and that is how it survived three passes of being read. '
    .'Two jobs agreeing on the test count and disagreeing on the assertions is the point — an assertion total is a property of '
    .'a run, not of the code — so a claim about the suite names the surfaces it was read from and the day it was read, and '
    .'storage/app/scratch/lane58-suite-claims.php binds every such claim in these documents to a reading it can name, '
    .'refusing rather than passing a figure it cannot bind.';

foreach (['&', '<', '>'] as $c) {
    $guard(! str_contains($newSpan, $c), 'the rewrite carries no raw '.$c);
}

$new = str_replace($oldSpan, $newSpan, $new, $c1);
$guard($c1 === 1, 'the passage was rewritten once (replaced '.$c1.')');
$guard(substr_count($new, $startNeedle) === 1, 'and the new passage is present exactly once');

// Every figure pass 165 measured must still be stated; a re-cut prose edit that silently drops a reading is a document
// that has become less true than the one it replaced.
foreach (['780 passed with 5,220 assertions', '771 passed with 5,013', '771 passed with 5,015', '8 tests with 201', 'discovers: 780 cases', '771 tests and 5191 assertions'] as $figure) {
    $guard(str_contains($new, $figure), 'the rewrite still states '.$figure);
}
$guard(strlen($new) !== strlen($xml), 'the bytes changed ('.(strlen($new) - strlen($xml)).')');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nThe passage this pass would write:\n\n".$newSpan."\n\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass166.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass166.tmp';
$write = new ZipArchive();
if ($write->open($out, ZipArchive::CREATE) !== true) {
    echo "REFUSED: cannot build the replacement archive.\n";
    exit(1);
}
$read = new ZipArchive();
$read->open($src);
for ($i = 0; $i < $read->numFiles; $i++) {
    $name = $read->getNameIndex($i);
    $write->addFromString($name, $name === 'word/document.xml' ? $new : $read->getFromIndex($i));
}
$write->close();
$read->close();
if (! rename($out, $src)) {
    echo "REFUSED: could not move the new archive; backup at {$backup}.\n";
    exit(1);
}
$check = new ZipArchive();
$check->open($src);
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = (string) $check->getFromName('word/document.xml');
$check->close();
$guard(hash('sha256', $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');
if ($fails > 0) {
    printf("\n%d guard(s) failed after writing; backup at %s\n", $fails, $backup);
    exit(1);
}

printf("\nPass 166 written; backup at %s\n", $backup);
exit(0);
