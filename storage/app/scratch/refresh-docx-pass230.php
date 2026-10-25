<?php

/**
 * Pass 230 — the other two copies of the same sentence, spelled differently.
 *
 * Pass 229 replaced one occurrence because it addressed the pair `264-271 , :311-317`, which is unique. The
 * document repeats the room-capacity sentence twice more with the comma tight against the number — `264-271,
 * :311-317`, which occurs exactly twice — and those carry the same wrong editing-half pointer: `:311-317` is
 * `updateSchedule()`'s temp-schedule build (`replicate()` :310, `fill()` :311), while the capacity warning the
 * sentence quotes is :327-333 ("Schedule was still updated" at :331).
 *
 * Three copies, two spellings, one range: the count is asserted before and the residue after, so a later reader
 * cannot mistake the remaining `:311-317` for a fourth copy of this claim. Every line is re-asserted here rather
 * than trusted from pass 229, because a pass that inherits another pass's measurements is how a stale claim
 * survives a "fix".
 *
 *   php storage/app/scratch/refresh-docx-pass230.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass230.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$bc = (array) file($root.'/app/Http/Controllers/Blocking/BlockingController.php');
$fails = 0;
foreach ([
    [269, '$room && $block->maxStudents > $room->capacity'],
    [270, 'Schedule was still added'],
    [311, '$tempSchedule->fill('],
    [327, "if (isset(\$validated['roomId']))"],
    [330, '$room && $block && $block->maxStudents > $room->capacity'],
    [331, 'Schedule was still updated'],
    [333, '}'],
] as [$n, $needle]) {
    $t = trim((string) ($bc[$n - 1] ?? ''));
    $ok = str_contains($t, $needle);
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

$needle = '264-271, :311-317';
$want = '264-271, :327-333';
$n = substr_count($xml, $needle);
printf("\n  needle [%s] occurs %d time(s) in document.xml (want 2)\n", $needle, $n);
if ($n !== 2) {
    echo "REFUSED: the count is not the two copies this pass measured.\n";
    exit(1);
}
$after = str_replace($needle, $want, $xml);
printf("  after: repaired pair occurs %d (want 2); the wide-spelled pair from pass 229 occurs %d (want 1 repaired)\n",
    substr_count($after, $want), substr_count($after, '264-271 , :327-333'));
if (substr_count($after, $want) !== 2 || substr_count($after, $needle) !== 0
    || substr_count($after, '264-271 , :327-333') !== 1) {
    echo "REFUSED: the consequence of the edit is not what this pass predicts.\n";
    exit(1);
}
printf("  `:311-317` left anywhere in the document: %d (want 0 for this file's citations)\n", substr_count($after, ':311-317'));

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass230.docx', [[$needle, $want, null, 2]], [], $apply);
