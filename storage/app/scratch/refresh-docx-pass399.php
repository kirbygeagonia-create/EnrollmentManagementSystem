<?php

/**
 * Docx pass 399 — retire a leg count this file has no business carrying.
 *
 * Pass 398's §1.1 row wrote that the rollback falsifier "answers seven legs and kills all seven". That is a
 * figure about what a harness prints, and no instrument binds it (`lane59-instrument-counts.php` judges a figure
 * only where the sentence names a tool from its own table, and a harness written today is not in it), so it would
 * rot the day a leg is added and nothing would say so. §1.1's own convention is to name the tool or state no
 * total, so the clause is re-worded to the property rather than the count.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass399.php [--apply]
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

require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$from = 'answers seven legs and kills all seven';
$to = 'kills each guard the probe carries';

$textEdits = [[$from, $to, null, 1]];
$engineBackup = $root.'/storage/app/scratch/pass399-engine-backup.docx';
docxApply($docx, $engineBackup, $textEdits, [], $apply);

if (! $apply) {
    echo "DRY RUN: one clause re-worded ({$from} => {$to}).\n";
    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: the written docx did not reopen.\n");
    exit(1);
}
$read = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($read === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty after the write.\n");
    exit(1);
}

$flat = static function (string $xml): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $xml, $tm);

    return html_entity_decode(implode(' ', $tm[1]), ENT_QUOTES | ENT_XML1);
};
$body = $flat($read);
$bad = [];
if (substr_count($body, $from) !== 0) {
    $bad[] = 'the retired clause still occurs '.substr_count($body, $from).' time(s).';
}
if (substr_count($body, $to) !== 1) {
    $bad[] = 'the new clause occurs '.substr_count($body, $to).' time(s), expected 1.';
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: read-back failed:\n  ".implode("\n  ", $bad)."\nRestore from {$engineBackup}.\n");
    if (! copy($engineBackup, $docx)) {
        fwrite(STDERR, "REFUSED: the restore failed — repair from {$engineBackup} by hand.\n");
    }
    exit(1);
}

echo "read-back: the retired count is gone and the property sentence is present once.\n";
