<?php

/**
 * Docx pass 246d: the config-file pointer pass 246 wrote moved under its own comment.
 *
 * Pass 246 cited `config/trustedproxy.php:34` for the line that answers `TRUSTED_PROXIES`. The comment above it then
 * grew by three lines — the vendor line numbers were taken out of that comment on purpose, because a pointer into
 * `vendor/` goes stale on a framework update and nothing in this repository would notice — so the cited line moved
 * to :37 and the sweep still called it clean, because line 34 exists and is not blank. Measured against the file
 * this time: `grep -n "proxies' =>"` answers 37.
 *
 *   php storage/app/scratch/refresh-docx-pass246d.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass246d.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass246d.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

// The line the new pointer must land on, read from the file rather than from arithmetic.
$cfg = file($root.'/config/trustedproxy.php') ?: [];
$at = 0;
foreach ($cfg as $i => $line) {
    if (str_contains((string) $line, "'proxies' =>")) {
        $at = $i + 1;

        break;
    }
}

if ($at === 0) {
    exit("REFUSED: config/trustedproxy.php answers no 'proxies' line, so there is nothing to point at.\n");
}

$zip = new ZipArchive();
if ($zip->open($docx) !== true) {
    exit("REFUSED: the document cannot be opened as an archive.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'reading the value it judges from config/trustedproxy.php:34;';
$to = 'reading the value it judges from config/trustedproxy.php:'.$at.';';

if ($from === $to) {
    echo "NOTHING TO DO: the pointer already carries the measured line {$at}.\n";
    exit(0);
}

$count = substr_count($xml, $from);
if ($count !== 1) {
    printf("NOTHING WRITTEN: the needle occurs %d time(s); this pass takes exactly one.\n", $count);
    exit(1);
}

$line = trim((string) ($cfg[$at - 1] ?? ''));
if (! str_contains($line, 'TRUSTED_PROXIES')) {
    exit("REFUSED: line {$at} of the config file does not read TRUSTED_PROXIES — it says: {$line}\n");
}

printf("asserted: config/trustedproxy.php:%d is %s\n", $at, $line);

$out = docxApply($docx, $backup, [[$from, $to]], [], $apply);
printf("%s\n", $apply ? 'WROTE: '.($out ?? 'see the engine report') : 'DRY RUN: the needle is unique and the edit is ready');
