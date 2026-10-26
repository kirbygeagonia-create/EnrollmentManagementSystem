<?php

/**
 * Docx pass 245: G-13 gets a check, and the owed-actor row points at it.
 *
 * The register's last install-time item has waited on "whoever provisions the school's server" since 2026-10-23,
 * and the correct answer (not yet deployed) cannot be improved by code. What code CAN do is stop the instruction
 * from being a paragraph someone has to remember: `ems:preflight` now prints a `Forwarded scheme` row that answers
 * OK when nothing is trusted and the host speaks HTTP, WARN when an https APP_URL sits beside no trusted address,
 * OK with a count when an address is named, and WARN for the wildcard. Two cells move, and nothing else: no range
 * statement, no register size, no family count, because no row was opened or closed.
 *
 * Both needles are unique in `word/document.xml` and are checked before anything is written. They go through the
 * `text` kind rather than a cell rewrite, so the styled runs inside each cell survive (a cell/para rewrite
 * flattens a block to one run — lane 69's rule).
 *
 *   php storage/app/scratch/refresh-docx-pass245.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass245.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass245.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

if (! is_file($docx)) {
    exit("REFUSED: the authority document is not where this pass expects it.\n");
}

$zip = new ZipArchive();
if ($zip->open($docx) !== true) {
    exit("REFUSED: the document cannot be opened as an archive.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [
        'SecurityHeadersTest.php; middleware comment corrected',
        'SecurityHeadersTest.php; middleware comment corrected; ems:preflight prints a Forwarded scheme row, which '
            .'WARNs when an https APP_URL sits beside no trusted proxy and refuses to call trustProxies(at: *) named '
            .'(measured 2026-10-26)',
    ],
    [
        'G-13 (install-time, DEPLOYMENT.md §5)',
        'G-13 (install-time, DEPLOYMENT.md §5; ems:preflight now answers whether the scheme can be decided)',
    ],
];

$fails = 0;
foreach ($edits as [$from, $to]) {
    $count = substr_count($xml, $from);
    if ($count !== 1) {
        printf("BAD  expected exactly 1 copy of %s, the archive holds %d\n", $from, $count);
        $fails++;
        continue;
    }
    if ($from === $to) {
        printf("BAD  the replacement is identical to the needle\n");
        $fails++;
        continue;
    }
    printf("ok   %s\n", substr($from, 0, 60));
}
if ($fails > 0) {
    exit("NOTHING WRITTEN: an assertion above failed, so the document was not touched.\n");
}

$pair = static fn (array $e): array => [$e[0], $e[1]];
$out = docxApply($docx, $backup, array_map($pair, $edits), [], $apply);
printf("%s\n", $apply ? 'WROTE: '.($out ?? 'see the engine report') : 'DRY RUN: the two needles are unique and the edits are ready');
