<?php

/**
 * Pass 229 — the twenty-fifth wrong target, from the queue row the anchor checker cannot bind.
 *
 * §26's room-capacity row says "Filing or editing a schedule that names a room too small for the block warns and
 * proceeds ( BlockingController.php:264-271 , :311-317 )" and then quotes both warning strings. The filing half is
 * right — :264-271 is `storeSchedule()`'s warning block (`if ($room && $block->maxStudents > $room->capacity)` at
 * :269, "Schedule was still added" at :270). The editing half is not: :311-317 is the *temp-schedule build for
 * conflict detection* (`$schedule->replicate()` at :310, `->fill(...)` at :311, the meetings relation at :313-315).
 * `updateSchedule()`'s capacity warning — the one whose words the same sentence quotes — is :327-333
 * (`if ($room && $block && $block->maxStudents > $room->capacity)` at :330, "Schedule was still updated" at :331),
 * returned warn-and-proceed at :345-347.
 *
 * `:311-317` occurs **3 times** in `document.xml` and `BlockingController.php:264-271` four times, so neither is a
 * usable needle on its own — this is the lane 69 offset trap. The pair `264-271 , :311-317` is contiguous and
 * unique, which is what the edit is anchored on. Every old and new line is asserted first, including the line the
 * wrong pointer lands on, so the pass proves the drift as well as the repair.
 *
 *   php storage/app/scratch/refresh-docx-pass229.php          # dry run
 *   php storage/scratch/refresh-docx-pass229.php --apply      # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$bc = (array) file($root.'/app/Http/Controllers/Blocking/BlockingController.php');
$fails = 0;
foreach ([
    // the filing half, which the document already cites correctly
    [264, '// Room capacity warning: advisory only'],
    [269, '$room && $block->maxStudents > $room->capacity'],
    [270, 'Schedule was still added'],
    // what the wrong pointer actually lands on: the conflict-detection build
    [310, '$tempSchedule = $schedule->replicate()'],
    [311, '$tempSchedule->fill('],
    [317, ''],
    // the editing half the sentence quotes, which the pointer should name
    [327, "if (isset(\$validated['roomId']))"],
    [330, '$room && $block && $block->maxStudents > $room->capacity'],
    [331, 'Schedule was still updated'],
    [333, '}'],
    [345, 'return $warning'],
    [346, "->with('warning', \$warning)"],
] as [$n, $needle]) {
    $t = trim((string) ($bc[$n - 1] ?? ''));
    $ok = $needle === '' || str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 64));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$needle = '264-271 , :311-317';
$want = '264-271 , :327-333';
$n = substr_count($xml, $needle);
printf("\n  needle [%s] occurs %d time(s) in document.xml (want 1)\n", $needle, $n);
if ($n !== 1) {
    echo "REFUSED: the pair is not unique, so this pass will not touch a bare number by itself.\n";
    exit(1);
}
// The after-check is scoped to the pair this pass replaces. An earlier draft asserted that `:311-317)` had
// disappeared DOCUMENT-WIDE, which is false on purpose: two other citations legitimately carry that range, and a
// guard that refuses its own correct edit is a guard pointed at the wrong consequence. What must go to zero is the
// pair, and what must appear exactly once is the repaired pair.
$after = str_replace($needle, $want, $xml);
$stillThere = substr_count($after, $needle);
$repaired = substr_count($after, $want);
printf("  after the edit: the pair would still occur %d time(s) (want 0) and the repaired pair %d time(s) (want 1)\n",
    $stillThere, $repaired);
if ($stillThere !== 0 || $repaired !== 1) {
    echo "REFUSED: the consequence of the edit is not what this pass predicts.\n";
    exit(1);
}
// The other two `:311-317` copies belong to other citations; say so, so nobody "fixes" them by global replace.
printf("  unrelated copies of `:311-317` left standing: %d (each belongs to a different file's citation)\n",
    substr_count($after, ':311-317'));

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass229.docx', [[$needle, $want]], [], $apply);
