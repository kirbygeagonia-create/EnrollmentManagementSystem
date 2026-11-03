<?php

/**
 * Pass 428 — the §18 lane-49 sentence re-taken against the tool it describes.
 *
 * `lane59-instrument-counts.php` went STALE on "370 lines", and reading the tool for the other half found a worse
 * defect than a drifted number: the sentence says "21 guards whose dash sits on a line this file cannot join", which
 * is the description of the BLIND bucket (16 rows: a ternary's `: '—'` tail written alone) wearing the number of the
 * UNBOUND bucket (21 rows: the reader cannot bind the guarded operand). The binder agreed with the number because it
 * matches on the word "guards", so a mismatched gloss sat under a green reading — the two-vocabulary defect reached
 * from a checker's label table rather than from the code.
 *
 * Both figures are re-taken from the run, not typed: the words are re-cut so each bucket is named by what it holds.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass428.php [--apply]
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

// The figures come from the instrument, in this run, rather than from the draft above.
$readings = json_decode((string) file_get_contents($root.'/storage/app/scratch/lane59-readings.json'), true);
$lane49 = $readings['lane49-zero-shown-as-absence'] ?? null;
if (! is_array($lane49)) {
    $fail('lane59-collect-readings.php has not recorded a lane49 reading; run it first, then this pass.');
}
$unread = (int) ($lane49['unread'] ?? -1);
$nonFig = (int) ($lane49['nonFigures'] ?? -1);
if ($unread < 0 || $nonFig < 0) {
    $fail("the recorded reading holds no unread/non-figure bucket: {$unread}/{$nonFig}.");
}

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open for the needle probe.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [
        '21 guards whose dash sits on a line this file cannot join',
        $unread.' guards whose tested operand this file cannot bind, plus the dashes written on a line of their own',
    ],
    [
        '370 lines where the tested operand is a name, code, date or error message',
        $nonFig.' lines where the tested operand is a name, code, date or error message',
    ],
];
// The first needle carries the old digit 21; if the reading has moved, its needle must move too, or the guard below
// would report "matched 0" against a document the pass had already corrected.
if ($unread !== 21) {
    $edits[0][0] = $unread.' guards whose dash sits on a line this file cannot join';
}

foreach ($edits as $i => [$from, $to]) {
    $hits = substr_count($xml, $from);
    if ($hits !== 1) {
        $fail('pre-flight: edit '.($i + 1)." needle occurred {$hits} times, expected exactly 1: [".mb_substr($from, 0, 60).'…]');
    }
    if (substr_count($xml, $to) !== 0) {
        $fail('pre-flight: edit '.($i + 1).' replacement is already in the document — this pass has run.');
    }
    if ($from === $to) {
        $fail('pre-flight: edit '.($i + 1).' would write its own needle, which changes nothing.');
    }
}

printf("%d edits prepared (unread %d, non-figure %d). %s\n", count($edits), $unread, $nonFig, $apply ? 'applying' : 'DRY RUN');
if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass428-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
// NO shutdown hook here. `docxApply()` already owns backup-and-restore when a pass fails, and a second guard that
// re-reads the file at exit to decide whether "my text is present" restored a CORRECT write on this box: the re-read
// transiently answers empty on OneDrive-backed bytes, the guard read that as a failed pass, and the document came
// back with the old figures while the pass had already printed its own success. A rescue that fires on the success
// path is worse than no rescue — it turns a landed edit into a silent revert. The read-back below is the check.
docxApply($docx, $backup, $edits, [], true);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not reopen for the read-back.');
}
$read = (string) $zip->getFromName('word/document.xml');
$zip->close();
foreach ($edits as $i => [$from, $to]) {
    if (substr_count($read, $from) !== 0) {
        $fail('read-back: edit '.($i + 1).' left its retired needle in the document.');
    }
    if (substr_count($read, $to) !== 1) {
        $fail('read-back: edit '.($i + 1).' replacement occurs '.substr_count($read, $to).' times, expected 1.');
    }
}
echo "WROTE both halves of the lane-49 blind-spot sentence, read from this run's figures.\n";
