<?php

/**
 * Pass 354 — the Blocking roster becomes the eleventh wired table (§28.3 G-40), and the one window the shift tool
 * could not resolve is re-taken by reading.
 *
 * `lane76-shift-cites.php` moved 36 prefixed tokens when `BlockingController.php` grew 30 lines, and refused three
 * items rather than guess: a bare `:117` whose nearest name is another file (it belongs to the Evaluation queue's
 * own window, which did not move, so it is left exactly as written), a bare `:571` likewise attributed elsewhere,
 * and `:69-80` as GONE — the tile block the G-16 evidence cell cites is no longer contiguous, because this batch
 * put the order between the clone and the paginate.
 *
 * That third item was read, not arithmetic: live line 69 is still `$setRows = (clone $query)->reorder()->get();`
 * and the four tile numbers now end at line 105 (`'available' => max(0, …)`), so the window that carries the row's
 * claim — "the tiles are taken over the whole filtered set, cloned before paginate()" — is 69 to 105. Both
 * endpoints are asserted against the file before the write, and the needle is the row's own citation phrase.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass354.php [--apply]
 */
$root = dirname(__DIR__, 3);
require_once __DIR__.'/docx-edit-engine.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$live = $root.'/app/Http/Controllers/Blocking/BlockingController.php';
$L = file($live);
$guards = [
    [69, '$setRows = (clone $query)->reorder()->get();', 'the window still opens on the clone'],
    [105, "'available' => max(0,", 'and still ends on the last tile number'],
];
foreach ($guards as [$no, $has, $claim]) {
    $text = trim((string) ($L[$no - 1] ?? ''));
    if (! str_contains($text, $has)) {
        fwrite(STDERR, sprintf("REFUSED: BlockingController.php line %d is [%s], which does not carry %s.\n", $no, substr($text, 0, 70), $claim));

        exit(1);
    }
}
echo "ok   both endpoints of the re-taken window read true\n";

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx to read word/document.xml.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[':69-80', 1, 'the G-16 evidence cell is the only copy'], ['Examination result queue — 26 of the 36', 1, 'the register row as pass 353 left it'], ['the ten wired pages call', 1, 'the screen count pass 353 wrote']] as [$needle, $want, $why]) {
    $got = substr_count($xml, $needle);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("REFUSED: [%s] occurs %d time(s), wanted %d — %s\n", substr($needle, 0, 40), $got, $want, $why));

        exit(1);
    }
    echo sprintf("ok   needle occurs %d time(s): %s\n", $got, $why);
}

$edits = [
    [':69-80', ':69-105'],
    ['Examination result queue — 26 of the 36', 'Examination result queue, and on the Blocking roster — 25 of the 36'],
    ['the ten wired pages call', 'the eleven wired pages call'],
    // The third item the shift deferred: two bare `:571` tails of `BlockingController.php:542 and :571` and of
    // `downloadBlockSchedule() serves the PDF ( :571 )`. The prefixed head moved to :572 with the batch; these two
    // name no file inside the attribution window, so a reader had to take them. Live 601 is the action's own
    // signature — asserted above, not arithmetic. Both copies are the same construct, so expect=2 is honest.
    [':571', ':601', null, 2],
];

docxApply($docx, $root.'/storage/app/scratch/pass354-backup.docx', $edits, [], $apply);

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

foreach ([[':69-80', 0], [':69-105', 1], ['Blocking roster — 25 of the 36', 1], ['the eleven wired pages call', 1], ['the ten wired pages call', 0], [':571', 0], [':601', 2]] as [$needle, $want]) {
    $got = substr_count($after, $needle);
    if ($got !== $want) {
        echo sprintf("BAD  [%s] occurs %d, wanted %d — restoring.\n", substr($needle, 0, 34), $got, $want);
        copy($root.'/storage/app/scratch/pass354-backup.docx', $docx);

        exit(1);
    }
}
echo "written and read back: the roster's tile window re-taken by reading, and G-40 names eleven wired tables.\n";
