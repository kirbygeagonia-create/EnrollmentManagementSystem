<?php

/**
 * Pass 300: the document's count of unwired queues moved when the Admission queue was wired.
 *
 * §1.1 and §28.3 both state how many of the 36 tables still offer no order, and both said 35 when the Registrar
 * queue was the only one wired. The register's figure and the row that justifies it move together — lane 61's
 * lesson, met again from the other side: a build change that ages a tally must reach the tally, not only the row.
 *
 * The pass prints each needle's own count and refuses unless it equals what the edit was written for, because a
 * half-applied register (one section stating two queues while its twin states one) is the worst docx defect.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass300.php [--apply]
 */

$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', array_slice($argv, 1), true);

$edits = [
    // [needle, replacement, expected document-wide count]
    ['wired; nothing else is. 35 of the 36 pages',
        'wired; the Admission queue joined it the same day. 34 of the 36 pages', 1],
    ['and on nothing else — 35 of the 36 pages',
        'and on the Admission queue — 34 of the 36 pages', 1],
    ['one of those twenty-nine queues sorts on the server today',
        'two of those twenty-nine queues sort on the server today', 1],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$bad = [];
foreach ($edits as $i => [$from, $to, $want]) {
    $n = substr_count($xml, $from);
    printf("edit %d needle occurs %d time(s) (want %d)\n", $i, $n, $want);
    if ($n !== $want) {
        $bad[] = "edit {$i} expects {$want} copies, the document holds {$n}: ".substr($from, 0, 60);
    }
}
if ($bad !== []) {
    fwrite(STDERR, "REFUSED: nothing written.\n  ".implode("\n  ", $bad)."\n");

    exit(1);
}

$payload = [];
foreach ($edits as [$from, $to, $want]) {
    $payload[] = [$from, $to, null, $want];
}

docxApply($docx, $root.'/storage/app/scratch/pass300-backup-'.date('Ymd-His').'.docx', $payload, [], $apply);
