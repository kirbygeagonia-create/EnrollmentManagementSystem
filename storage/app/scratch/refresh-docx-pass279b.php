<?php

/**
 * Pass 279b: the one pointer lane76 deliberately deferred.
 *
 * The shift tool re-points every citation it can attribute, and refuses to guess at a bare
 * continuation whose owning file sits further than its byte window in document.xml. ROW 724's
 * `ClearanceController.php:383 and :408` is exactly that: the download action moved with the
 * batch, so the bare half stayed three lines behind. This pass takes it by reading: live line 411
 * is asserted to be downloadSlip()'s signature before the number is written.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass279b.php [--apply]
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

$live = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Clearance/ClearanceController.php'));

$assertions = [
    411 => 'downloadSlip',
    383 => 'printSlip',
];

foreach ($assertions as $line => $claim) {
    $body = $live[$line - 1] ?? '(past end)';
    if (! str_contains($body, $claim)) {
        fwrite(STDERR, "REFUSED: live line {$line} reads [".trim($body)."], which does not carry [{$claim}].\n");

        exit(1);
    }
    echo "assert line {$line} carries {$claim}: ".trim($body)."\n";
}

$from = 'and :408';
$to = 'and :411';
// The second bare copy is the print-route table's `( :408 )`. The shift tool skipped the whole token
// because --skip names the token, not one of its copies, so both halves of the deferral land here.
$edits = [
    [$from, $to],
    [':408 )', ':411 )'],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answered an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ($edits as [$needle, $want]) {
    if (substr_count($xml, $needle) !== 1) {
        fwrite(STDERR, 'REFUSED: ['.$needle.'] occurs '.substr_count($xml, $needle)." time(s); this pass writes unique needles.\n");

        exit(1);
    }
}

// The third `:408` in the file is Preflight.php:408-484, a different file's range that merely starts
// with these four characters. Anything else matching :408 means this pass is about to hit a pointer
// it was not told about.
if (substr_count($xml, ':408') !== 3 || substr_count($xml, ':411') !== 0) {
    fwrite(STDERR, 'REFUSED: expected 3 copies of :408 (two bare pointers plus the Preflight range) and 0 of :411;'
        .' measured '.substr_count($xml, ':408').' and '.substr_count($xml, ':411').".\n");

    exit(1);
}

printf("%s: §23's deferred bare pointer takes 411\n", $apply ? 'APPLY' : 'DRY RUN');

$backup = $root.'/storage/app/scratch/pass279b-backup-'.date('Ymd-His').'.docx';

if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass279b-dry.docx', $edits, [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if (substr_count($read, ':411') !== 2 || substr_count($read, ':408') !== 1) {
    fwrite(STDERR, 'REFUSED READ-BACK: [:411] '.substr_count($read, ':411').' copy(s), [:408] '
        .substr_count($read, ':408')." copy(s) (want 2 and 1 — the survivor is Preflight.php:408-484); restore from {$backup}.\n");

    exit(1);
}

echo "\npass 279b applied: both bare download-action pointers now read :411, and the Preflight range is untouched.\n";
