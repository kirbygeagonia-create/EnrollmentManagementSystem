<?php

/**
 * Docx pass 246b: name the file in the pointer pass 246 just wrote, because a lengthened sentence made it blind.
 *
 * Pass 246 added a clause to §18's preflight paragraph and put the new pointer in as a bare `:133-188`. The bounds
 * sweep attributes a bare number to the file named most recently before it on the same dump line, within 160
 * characters — and the clause it lengthened pushed the number past that window, so the run went from 1 blind spot
 * (the by-design example in §1.1) to 2 while still printing 0 line problems. That is pass 232c's lesson met from
 * the inside: after any pass that lengthens a sentence, read the blind-list delta, not just the problem count.
 *
 * The fix is the one the document already uses: name the file. The needle is unique in `word/document.xml`.
 *
 *   php storage/app/scratch/refresh-docx-pass246b.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass246b.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass246b.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$zip = new ZipArchive();
if ($zip->open($docx) !== true) {
    exit("REFUSED: the document cannot be opened as an archive.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [
        'and since 2026-10-26 the list answers one more question at :133-188, whether a forwarded scheme can be believed, reading the value it judges from config/trustedproxy.php:34;',
        'and since 2026-10-26 the list answers one more question at Preflight.php:133-188, whether a forwarded scheme '
            .'can be believed, reading the value it judges from config/trustedproxy.php:34;',
    ],
];

$fails = 0;
foreach ($edits as [$from, $to]) {
    $count = substr_count($xml, $from);
    if ($count !== 1) {
        printf("BAD  expected exactly 1 copy of %s, the archive holds %d\n", substr($from, 0, 70), $count);
        $fails++;
    }
}

if ($fails > 0) {
    exit("NOTHING WRITTEN: the needle is not addressable, so nothing was touched.\n");
}

$pair = static fn (array $e): array => [$e[0], $e[1]];
$out = docxApply($docx, $backup, array_map($pair, $edits), [], $apply);
printf("%s\n", $apply ? 'WROTE: '.($out ?? 'see the engine report') : 'DRY RUN: the needle is unique and the edit is ready');
