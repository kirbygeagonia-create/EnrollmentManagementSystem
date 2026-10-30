<?php

/**
 * Pass 307: the day's §1.1 row stops quoting a figure that was never measured.
 *
 * `lane58-suite-claims.php --ci` refused CI run 40861302826 on this sentence, because a quoted number is still a
 * number to the classifier: `the report of the batch said "pinned by four cases"` reads as a present-tense class
 * claim even though it exists to record that the count was wrong when it was written. The disclosure is the point,
 * so the figure goes and the sentence keeps its meaning — lane 58's own rule, applied to a quotation: state no total
 * rather than one the runner must judge.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass307.php [--apply]
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

$from = 'and the report of the batch said "pinned by four cases" when the number had been written from intent.';
$to = 'and the report of the batch had typed a case count from intent before any run answered it.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answered an error code, not true).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

foreach ([[$from, 1], ['pinned by four cases', 1]] as [$needle, $want]) {
    $hits = substr_count($xml, $needle);
    if ($hits !== $want) {
        fwrite(STDERR, "REFUSED: the needle occurs {$hits} time(s), expected {$want}: [{$needle}]\n");

        exit(1);
    }
}

printf("%s: the §1.1 row's quoted case count becomes an unquoted record\n", $apply ? 'APPLY' : 'DRY RUN');

$backup = $root.'/storage/app/scratch/pass307-backup-'.date('Ymd-His').'.docx';
if ($apply && ! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

docxApply($docx, $apply ? $backup : $root.'/storage/app/scratch/pass307-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the docx after the write.\n");

    exit(1);
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$guards = [
    ['pinned by four cases', 0, 'the quoted figure must be gone'],
    ['typed a case count from intent', 1, 'the new wording must be present'],
    ['before any run answered it', 1, 'the new wording must reach its end'],
];

$failed = false;
foreach ($guards as [$needle, $want, $why]) {
    $hits = substr_count($after, $needle);
    if ($hits !== $want) {
        printf("GUARD FAILED (%s): [{$needle}] occurs %d, want %d\n", $why, $hits, $want);
        $failed = true;
    }
}

if (substr_count($after, "\xE2\x80\x9C") !== substr_count($xml, "\xE2\x80\x9C")) {
    echo "GUARD FAILED: the smart-quote count moved, so a run boundary was disturbed.\n";
    $failed = true;
}

if ($failed) {
    fwrite(STDERR, "RESTORE the backup and re-cut the needle.\n");

    exit(1);
}

echo 'APPLIED: one sentence re-worded, both guards satisfied.'."\n";
