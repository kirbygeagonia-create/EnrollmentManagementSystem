<?php

/**
 * Pass 429 — one clause added to this batch's own §1.1 row.
 *
 * The row as written says the Part IV marks were re-taken against the delivered cases and that the answers file's
 * claims were weighed. What is now true and was not recorded: the copy itself is read back and every concept row is
 * paired BY NAME with the answer file, with the ticked labels compared in both directions. That is the difference
 * between "the file says it is demonstrated" and "the paper a panelist reads has the demonstration box ticked", which
 * is the half the owner asked about.
 *
 * This is an APPEND, so the retired needle is expected to SURVIVE as the head of the new sentence — the per-edit
 * residual rule pass 335 and pass 341 had to learn: a read-back demanding the needle disappear refuses a correct
 * write and restores its own backup.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass429.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        // Set here, not only in the guard below: this line was missing, so `--apply` was accepted, the strict parser
        // was satisfied, and the pass printed "DRY RUN" and wrote nothing — the accepted-but-inert flag this file has
        // refused five times, arrived at in a script written minutes earlier.
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'the answers file\'s artefact claims were weighed by ';
$to = 'the copy was read back and all twelve Part IV concept rows were paired by name with the answer file, the '
    .'ticked labels compared in both directions, and then the answers file\'s artefact claims were weighed by ';

if (substr_count($xml, $from) !== 1) {
    $fail('the needle ['.$from.'] occurred '.substr_count($xml, $from).' times, expected exactly 1 — read the row before appending.');
}
if (substr_count($xml, $to) !== 0) {
    $fail('this pass has already run: the added clause is in the document.');
}
// The appended clause must not smuggle a citation shape the checker would then demand a live line for.
if (preg_match('/[A-Za-z0-9_]+\.(php|jsx|md|mjs|json):\d/', $to) === 1) {
    $fail('the new clause carries a File.php:NN citation this pass cannot verify.');
}
printf("one append prepared (%d bytes). %s\n", strlen($to), $apply ? 'applying' : 'DRY RUN');
if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass429-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
docxApply($docx, $backup, [[$from, $to]], [], true);

$z = new ZipArchive;
if ($z->open($docx) !== true) {
    $fail('the docx did not reopen for the read-back.');
}
$read = (string) $z->getFromName('word/document.xml');
$z->close();
if (substr_count($read, $to) !== 1) {
    $fail('read-back: the added clause occurs '.substr_count($read, $to).' times, expected 1.');
}
// An append keeps its needle: the head of the new sentence is the old phrase, so its count must still be one.
if (substr_count($read, $from) !== 1) {
    $fail('read-back: the append should have preserved its needle; ['.$from.'] now occurs '.substr_count($read, $from).' times.');
}
echo "WROTE the label-pairing clause into this batch's own §1.1 row.\n";
