<?php

/**
 * Pass 381 — the three BlockingController pointers `lane76-shift-cites.php` would not write, taken by reading.
 *
 * The shift tool rewrote 37 tokens into this file and refused the run until every item was resolved, which is the
 * behaviour that keeps a half-moved citation from looking valid. Three items were outside a number needle:
 *
 *   `:591 and :601` and `serves the PDF ( :601 )` — both are the same cited method, `downloadBlockSchedule()`,
 *     whose HEAD line 601 is byte-identical to working line 620 (read on both sides). The second one the tool
 *     attributed to `RegistrarController.php` because that name sits nearest before it on the dump line; opening
 *     the cell shows `BlockingController.php:591` one clause earlier in the SAME cell, so the tool's nearest-name
 *     guess was wrong and the sentence owns the pointer. This is lane 70's mis-attribution, arriving as a
 *     deferral rather than a complaint.
 *   `filters its roster by it ( :117 )` — HEAD line 117 is a docblock opener (`/**`), so the tool reported
 *     AMBIGUOUS across the 16 places that token appears and refused rather than picking one. The claim is the
 *     roster's year-level filter, which is `->where('yearLevel', $block->yearLevel)` — HEAD line 147, working line
 *     148. So this pointer was a wrong target BEFORE this batch touched the file, under a number that always
 *     landed on a live line of the right file.
 *
 * Each needle was probed to occur exactly once in `document.xml` before this pass was written.
 * Dry run by default; --apply writes after a timestamped backup.
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass381-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $GLOBALS['argv'], true);

$edits = [
    ['BlockingController.php:591 and :601', 'BlockingController.php:591 and :620', null, 1],
    ['serves the PDF ( :601 )', 'serves the PDF ( :620 )', null, 1],
    ['roster by it ( :117 )', 'roster by it ( :148 )', null, 1],
];

foreach ($edits as $i => $e) {
    [$from, $to, $anchor, $expect] = $e;
    if ($anchor !== null && ! is_string($anchor)) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": slot 3 is the anchor and must be a string or null.\n");
        exit(1);
    }
    if ($from === $to) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": the replacement is identical to the needle.\n");
        exit(1);
    }
    if ($expect !== 1) {
        fwrite(STDERR, "REFUSED text ".($i + 1).": every needle here is unique or the pass does not run.\n");
        exit(1);
    }
}

$res = docxApply($src, $backup, $edits, [], $apply);
if ($res === false) {
    fwrite(STDERR, "REFUSED: the engine would not apply this pass.\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    return;
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    fwrite(STDERR, "REFUSED: could not re-open the docx to read it back.\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$text = html_entity_decode(preg_replace('/<[^>]+>/', '', (string) $xml) ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

// Read-back: each retired needle gone, each new one present once. `:601` is checked document-wide because both of
// its copies were this pass's business; `:117` is not, since other files legitimately carry that line number.
foreach (['BlockingController.php:591 and :620' => 1, 'serves the PDF ( :620 )' => 1,
    'roster by it ( :148 )' => 1, 'and :601' => 0, 'serves the PDF ( :601 )' => 0,
    'roster by it ( :117 )' => 0] as $needle => $want) {
    $got = substr_count($text, $needle);
    if ($got !== $want) {
        fwrite(STDERR, "POST-READ FAILED: `{$needle}` occurs {$got} time(s), expected {$want}.\n");
        exit(1);
    }
    echo "read back: `{$needle}` => {$got}\n";
}

// The pointer this pass calls a wrong target: assert the line it now names really holds the roster filter, so the
// re-point is a reading and not arithmetic.
$live = (array) file($root.'/app/Http/Controllers/Blocking/BlockingController.php');
if (! str_contains((string) $live[147], "where('yearLevel', \$block->yearLevel)")) {
    fwrite(STDERR, 'REFUSED: line 148 does not hold the roster\'s year-level filter; it reads '
        .trim((string) $live[147])."\n");
    exit(1);
}
echo "line 148 verified: ".trim($live[147])."\n";
echo "backup: {$backup}\n";
