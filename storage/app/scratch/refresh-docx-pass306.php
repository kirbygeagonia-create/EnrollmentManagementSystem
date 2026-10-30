<?php

/**
 * Pass 306: today's §1.1 row gains the one sentence that came after it was written.
 *
 * The row records lanes 90 to 96. Since it landed, G-47's ownership half closed: `NotificationController::markRead()`
 * declined to write another desk's notice and then answered `{"ok": true}` regardless, and it now refuses with a
 * named 403. That is a promise about what a desk may do, so it belongs in the revision record rather than only in
 * the matrix — and it is appended to the row that already stands, because a second row for the same day would
 * double-count the register the way pass 173's did.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass306.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

require __DIR__.'/docx-edit-engine.php';

$from = 'asked on the 2026-10-29 sheet.';
$to = 'asked on the 2026-10-29 sheet. Also closed the same day, on the ownership side of prompt §8: the single mark-read endpoint declined to write another desk’s notice and then answered ok regardless, so a bell could grey out a notice nobody cleared; it now refuses by name, and the row’s own read_at is what the test asserts.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answered an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$hits = substr_count($xml, $from);
if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: the needle occurs {$hits} time(s), expected exactly 1: [{$from}]\n");

    exit(1);
}

printf("%s: the day's §1.1 row gains the mark-read sentence\n", $apply ? 'APPLY' : 'DRY RUN');

$backup = $root.'/storage/app/scratch/pass306-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass306-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$check = new ZipArchive;
$check->open($docx);
$after = (string) $check->getFromName('word/document.xml');
$check->close();

$readAt = substr_count($after, 'read_at is what the test asserts');
if (substr_count($after, $to) !== 1 || $readAt !== 1) {
    fwrite(STDERR, "REFUSED: the written document does not carry the sentence ({$readAt}); backup is {$backup}.\n");

    exit(1);
}

echo "WRITTEN and verified against the archive.\n";
