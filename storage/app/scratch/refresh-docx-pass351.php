<?php

/**
 * Pass 351 — the two bare citation tails `lane76-shift-cites.php` deferred on the Evaluation wiring.
 *
 * The batch added 34 lines to `EvaluationController.php` (the `$sortable` allow-list, the `ListSort::apply()`
 * call and the two echo keys), and the shift tool moved every prefixed window it could attribute. Two pointers
 * are BARE continuations — the document spells them `the act at :1136-1177` and `profile capture ( :566 )` with
 * no file name inside the 160-character window the attribution rule reads — so the tool refused them by design
 * rather than guessing an owner. This pass takes them by reading.
 *
 * What was read, in both versions of the file:
 *   `confirmClearance()` is HEAD line 1136 and live line 1170 (its closing brace moved with it, 1177 -> 1211),
 *   and BOTH copies of `:1136-1177` in the document name that method — §2's requirement row R-3 and §28's
 *   clearance-blocks row — so `expect=2` is the honest instruction, not a shortcut.
 *   `:566` occurs TWICE document-wide and the two copies have different owners: `AdmissionController.php:552-621`
 *   carries "the seat guard at :566" (its own line, unmoved by this batch) and the walkthrough row carries
 *   "evaluatedBy written by profile capture ( :566 )". Live `EvaluationController.php` writes `evaluatedBy` in
 *   `captureProfile()` at line 600. So only the second copy moves, and it is addressed by the anchor the shift
 *   just wrote — `:794-804`, the propose-window pointer in the same cell.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass351.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$live = $root.'/app/Http/Controllers/Evaluation/EvaluationController.php';
$head = __DIR__.'/lane76-head-EvaluationController.php';
$L = file($live);
$H = file($head);

// Asserted before anything is written: each new number must carry the claim its own sentence names.
$guards = [
    ['file' => $live, 'line' => 1170, 'has' => 'function confirmClearance(', 'claim' => 'the act at :1170-1211'],
    ['file' => $live, 'line' => 1211, 'has' => '}', 'claim' => 'the window ends at the method brace'],
    ['file' => $live, 'line' => 600, 'has' => "'evaluatedBy' => Auth::user()->userId", 'claim' => 'evaluatedBy written by profile capture'],
    ['file' => $head, 'line' => 1136, 'has' => 'function confirmClearance(', 'claim' => 'HEAD 1136 really was that signature'],
    ['file' => $head, 'line' => 566, 'has' => "'evaluatedBy' => Auth::user()->userId", 'claim' => 'HEAD 566 really was that write'],
];
foreach ($guards as $g) {
    $lines = $g['file'] === $live ? $L : $H;
    $text = trim((string) ($lines[$g['line'] - 1] ?? ''));
    if (! str_contains($text, $g['has'])) {
        fwrite(STDERR, sprintf(
            "REFUSED: line %d of %s is [%s], which does not carry %s.\n",
            $g['line'],
            basename($g['file']),
            substr($text, 0, 70),
            $g['claim']
        ));

        exit(1);
    }
}

// `:794-804` is the anchor only if the shift actually wrote it, and `:1136-1177` only if both copies still stand.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx to read word/document.xml.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([['Subjects proposed; the evaluator signs the form', 1, 'the walkthrough cell that owns the profile-capture tail — `:794-804` occurs three times document-wide and anchors nothing'], [':1136-1177', 2, 'both bare tails of confirmClearance()'], [':566', 2, 'two owners, only one of them ours']] as [$needle, $want, $why]) {
    $got = substr_count($xml, $needle);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: %s occurs %d time(s), wanted %d — %s.\n", $needle, $got, $want, $why));

        exit(1);
    }
    echo sprintf('ok   needle %s occurs %d time(s): %s', $needle, $got, $why)."\n";
}

$edits = [
    // Both copies of the confirmClearance() tail: §2's R-3 row and §28's clearance row.
    [':1136-1177', ':1170-1211', null, 2],
    // One copy of the profile-capture tail, addressed by the walkthrough cell that owns it — the other copy of
    // `:566` belongs to `AdmissionController.php:552-621`, which this batch never touched.
    [':566', ':600', 'Subjects proposed; the evaluator signs the form'],
];

docxApply($docx, $root.'/storage/app/scratch/pass351-backup.docx', $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

// Read-back: the retired numbers are gone the right number of times and the new ones arrived.
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the written docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$checks = [
    [':1136-1177', 0],
    [':1170-1211', 2],
    [':566', 1],
    [':600', 1],
];
$bad = 0;
foreach ($checks as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf('BAD  %s occurs %d time(s), wanted %d — restoring the backup.'."\n", $needle, $got, $want);
        $bad++;
    }
}
if ($bad > 0) {
    copy($root.'/storage/app/scratch/pass351-backup.docx', $docx);
    fwrite(STDERR, "REFUSED: the read-back failed, so the backup was restored.\n");

    exit(1);
}
echo 'written and read back: both tails re-pointed, the AdmissionController :566 left standing.'."\n";
