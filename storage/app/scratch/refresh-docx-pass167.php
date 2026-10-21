<?php

/**
 * Pass 167 — §28.3's G-18 row states lane 49's two counts as the tool prints them today.
 *
 * The row says the checker "prints its own blind spots: 18 guards whose dash sits on a line this file cannot join,
 * 355 lines where the tested operand is a name". Lane 49, run against this tree on 2026-10-20, prints 19 and 340.
 * The figures were right when pass 137 wrote them and no gate watched them afterwards — which is the whole of lane 59:
 * AGENTS.md carried the same sentence's numbers, and eight of its instrument figures had drifted the same way.
 *
 * Both numbers are corrected in one needle, because they sit in one clause and one offset cannot carry two edits.
 * The replacement values are parsed out of the tool's own log rather than typed, so the pass cannot write a number
 * the tool did not print.
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

$log = (string) @file_get_contents($root.'/storage/app/scratch/lane59-lane49-zero-shown-as-absence.log');
$unread = preg_match('/Not read \(a dash on the line, no recoverable operand\): (\d+)/', $log, $u) === 1 ? (int) $u[1] : null;
$nonFig = preg_match('/Not a money\/percent figure the guard itself prints: (\d+)/', $log, $n) === 1 ? (int) $n[1] : null;
$guard($unread !== null && $unread > 0, 'lane 49 answers an unread-guard count ('.var_export($unread, true).')');
$guard($nonFig !== null && $nonFig > 0, 'and a non-figure count ('.var_export($nonFig, true).')');
$guard(preg_match('/Guards ending in a dash: (\d+)/', $log, $d) === 1 && (int) $d[1] > 0,
    'and the dash-guard total the same run prints ('.($d[1] ?? '?').')');

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

$old = 'prints its own blind spots: 18 guards whose dash sits on a line this file cannot join, 355 lines where the tested operand is a name';
$newText = 'prints its own blind spots: '.$unread.' guards whose dash sits on a line this file cannot join, '.$nonFig
    .' lines where the tested operand is a name';
$guard(substr_count($new, $old) === 1, 'the clause is present exactly once before the edit');
$new = str_replace($old, $newText, $new, $c);
$guard($c === 1, 'and was rewritten once (replaced '.$c.')');
$guard(str_contains($new, $newText), 'the rewritten clause carries the measured pair');
$guard(! str_contains($new, 'blind spots: 18 guards'), 'the old 18 is gone from that clause');
$guard(substr_count($new, 'nineteen rules') === 1, 'the unrelated “nineteen rules” sentence is untouched');
// Not a length check: 18 → 19 and 355 → 340 both keep the byte count identical, so a pass that compared sizes would
// report "no change" on a correct edit. The bytes themselves differ, and that is the fact worth asserting.
$guard($new !== $xml, 'the bytes changed despite identical lengths ('.(strlen($new) - strlen($xml)).')');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nThis pass would write:\n\n  ".$newText."\n\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass167.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass167.tmp';
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
printf("\nPass 167 written; backup at %s\n", $backup);
exit(0);
