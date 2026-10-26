<?php

/**
 * Docx pass 246c: re-point the G-13 owed-actor row from DEPLOYMENT.md §5 to §2.
 *
 * Pass 245 wrote that citation while it was adding the preflight row, and it named the wrong section: §5 is
 * "Queue worker (audit §4.7)" and the proxy instruction has lived in §2, "Environment configuration", since
 * 2026-10-17. Measured against the file's own headings — §1 Requirements, §2 Environment, §3 Install,
 * §4 SysAdmin, §5 Queue worker, §6 Printing, §7 CI/CD, §8 Smoke test — and the row's other two §5 neighbours are
 * genuinely about the queue, so this is the one that means the scheme question.
 *
 * No instrument can see this shape: lane 37 judges `§`-references *inside* the document, `repo-file-line-citations.php`
 * judges `File.php:NN` pointers, and a markdown section number is neither. It is found by reading a row's citation
 * against the file it points at, which is what §12's Gate-4 sentence and the 2026-10-11 print sheet both came from.
 *
 *   php storage/app/scratch/refresh-docx-pass246c.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass246c.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass246c.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$zip = new ZipArchive();
if ($zip->open($docx) !== true) {
    exit("REFUSED: the document cannot be opened as an archive.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'G-13 (install-time, DEPLOYMENT.md §5; ems:preflight now answers whether the scheme can be decided)';
$to = 'G-13 (install-time, DEPLOYMENT.md §2 — where the proxy instruction lives; ems:preflight now answers whether '
    .'the scheme can be decided, and since the same date the value it reads is the TRUSTED_PROXIES env var)';

$count = substr_count($xml, $from);
if ($count !== 1) {
    printf("NOTHING WRITTEN: the needle occurs %d time(s), and this pass only knows how to take exactly one.\n", $count);
    exit(1);
}

$out = docxApply($docx, $backup, [[$from, $to]], [], $apply);
printf("%s\n", $apply ? 'WROTE: '.($out ?? 'see the engine report') : 'DRY RUN: the needle is unique and the edit is ready');
