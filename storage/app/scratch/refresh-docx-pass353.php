<?php

/**
 * Pass 353 — the Examination result queue becomes the tenth wired table (§28.3 G-40).
 *
 * Two jobs, both from the same batch:
 *
 * (a) `lane76-shift-cites.php` moved 39 prefixed citation tokens when `ExamController.php` grew 36 lines, and
 * deferred `:388-396` twice as BARE-DEFERRED — the document spells it `and :388-396` in two different rows, with
 * another file's name inside the 160-character window in one of them, so the attribution rule will not guess. Both
 * copies name the same construct: the departmental guard, whose BR9 comment sits at live line 424 and whose
 * `if ($course->requiresEntranceExam)` at 432. Read in both versions before writing: HEAD 388 is that comment and
 * HEAD 396 is the closing line of the guard's opening, so the pair moves whole.
 *
 * (b) The register row named nine screens and stated 27 of 36 still offer no order; the examination desk makes it
 * ten and 26. The same sentence in §28.3 is the third copy of this tally in the document, which is why the needle is
 * the phrase and not the digit — lane 61's lesson that a register count lives in more than one place.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass353.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$live = $root.'/app/Http/Controllers/Exam/ExamController.php';
$head = __DIR__.'/lane76-head-ExamController.php';

$L = file($live);
$H = file($head);
$guards = [
    [$L, 424, '// BR9: Stage 1 before Stage 2', 'the deferred tail opens on the BR9 comment'],
    [$L, 432, 'if ($course->requiresEntranceExam) {', 'and ends on the departmental guard'],
    [$H, 388, '// BR9: Stage 1 before Stage 2', 'HEAD 388 really was that comment'],
    [$H, 396, 'if ($course->requiresEntranceExam) {', 'HEAD 396 really was that guard'],
];
foreach ($guards as [$lines, $no, $has, $claim]) {
    $text = trim((string) ($lines[$no - 1] ?? ''));
    if (! str_contains($text, $has)) {
        fwrite(STDERR, sprintf("REFUSED: line %d is [%s], which does not carry %s.\n", $no, substr($text, 0, 70), $claim));

        exit(1);
    }
}
echo "ok   four line assertions hold in both versions of the file\n";

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx to read word/document.xml.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // Both bare copies of the departmental guard.
    [':388-396', ':424-432', null, 2],
    // The register row: the examination desk joins the list and the tally moves with it. An append keeps its head.
    ['and on the Department Evaluation queue — 27 of the 36 pages that use the shared table still offer no order',
        'and on the Department Evaluation queue, and on the Examination result queue — 26 of the 36 pages that use the shared table still offer no order'],
    ['the nine wired pages call', 'the ten wired pages call'],
];

foreach ($edits as $i => $edit) {
    $want = $edit[3] ?? 1;
    $got = substr_count($xml, $edit[0]);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: needle %d occurs %d time(s), wanted %d [%s]\n", $i, $got, $want, substr($edit[0], 0, 60)));

        exit(1);
    }
    echo sprintf("ok   needle %d occurs %d time(s)\n", $i, $got);
}

docxApply($docx, $root.'/storage/app/scratch/pass353-backup.docx', $edits, [], $apply);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen the written docx.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$checks = [
    [':388-396', 0, 'both bare tails moved'],
    [':424-432', 2, 'and arrived in both rows'],
    ['Department Evaluation queue, and on the Examination result queue — 26 of the 36', 1, 'the new tally in the register row'],
    ['— 27 of the 36 pages', 0, 'the retired tally must not survive'],
    ['the ten wired pages call', 1, 'the screen count'],
    ['the nine wired pages call', 0, 'the retired screen count'],
];
$bad = 0;
foreach ($checks as [$needle, $want, $why]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — %s\n", substr($needle, 0, 36), $got, $want, $why);
        $bad++;
    }
}
if ($bad > 0) {
    copy($root.'/storage/app/scratch/pass353-backup.docx', $docx);
    fwrite(STDERR, "REFUSED: the read-back failed, so the backup was restored.\n");

    exit(1);
}
echo "written and read back: the examination queue is the tenth wired table and both bare tails are live.\n";
