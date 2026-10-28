<?php

/**
 * Pass 274: §1.1's new row states no harness total, because the harness grew after the row was written.
 *
 * The row was typed when `mutate-lane70-queue-reads.php` had eleven legs; the ordinal defect it now proves added
 * two more before the commit. That is lane 58's and lane 59's rule arriving in the ordinary way — a figure that
 * belonged to one run outlives the run — and the honest answer is to name the instrument and not its count, since
 * the harness prints only its failures and no leg total.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass274.php [--apply]
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

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'with none stale, proved by all eleven legs of its harness';
$to = 'with none stale, proved leg by leg by its harness, which renumbers a whole dump and requires the recorded '
    .'reads to survive it, and plants the old keying back and requires them not to';

$n = substr_count($xml, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSED: needle matched {$n} place(s), expected exactly 1.\n");

    exit(1);
}
if (substr_count($xml, $to) !== 0) {
    fwrite(STDERR, "REFUSED: this pass's own wording is already in the document.\n");

    exit(1);
}

$updated = str_replace($from, $to, $xml);
if (substr_count($updated, $from) !== 0 || substr_count($updated, $to) !== 1) {
    fwrite(STDERR, "REFUSED: the after-count is wrong (old ".substr_count($updated, $from).', new '
        .substr_count($updated, $to).").\n");

    exit(1);
}

// No leg total is typed anywhere in the replacement, because the harness prints its failures and no census.
if (preg_match('/\b(eleven|thirteen|twelve)\b/i', $to)) {
    fwrite(STDERR, "REFUSED: the replacement still states a leg count.\n");

    exit(1);
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $updated);
    $b = substr_count($updated, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s§1.1's row now names the harness and not its count\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass274-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}
$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 274 applied.\n";
