<?php

/**
 * Pass 88 — one line of drift, corrected.
 *
 * The pass-87 citation set was measured before the last PHPStan fix shortened the throw by
 * one line and lengthened the guard by one, which moved every span below line 220 by one.
 * Each target here was re-read from the file by the statement it names, and the counts are
 * asserted, not assumed.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$cites = [
    ['WorkflowService.php:182-278', 'WorkflowService.php:182-279', 6, 'signStep(), whole method'],
    ['WorkflowService.php:206-220', 'WorkflowService.php:206-221', 1, 'the already-signed guard'],
    ['the office test at :236', 'the office test at :237', 4, 'the office-scope test, short form'],
    ['WorkflowService.php:236-240', 'WorkflowService.php:237-241', 1, 'the office-scope throw'],
    ['WorkflowService.php:243-253', 'WorkflowService.php:243-254', 1, 'BR13 order check'],
    ['WorkflowService.php:256-259', 'WorkflowService.php:256-260', 1, 'the sign write'],
    ['WorkflowService.php:268-275', 'WorkflowService.php:268-276', 2, 'completion of the workflow'],
    ['WorkflowService.php:294-301', 'WorkflowService.php:295-302', 1, 'resolve by officeId, return null'],
    ['WorkflowService.php:223-227', 'WorkflowService.php:224-228', 1, 'the academic-signer exception'],
];

$fails = 0;
$statements = [];

foreach ($cites as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    $statements[] = $label;
    printf("edit ok  %-40s  %dx → %s\n", $label, $expect, $to);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no drifted span survives' => substr_count($xml, $esc('WorkflowService.php:182-278')) === 0
        && substr_count($xml, $esc('WorkflowService.php:206-220')) === 0
        && substr_count($xml, $esc('the office test at :236')) === 0
        && substr_count($xml, $esc('WorkflowService.php:236-240')) === 0
        && substr_count($xml, $esc('WorkflowService.php:243-253')) === 0
        && substr_count($xml, $esc('WorkflowService.php:256-259')) === 0
        && substr_count($xml, $esc('WorkflowService.php:268-275')) === 0
        && substr_count($xml, $esc('WorkflowService.php:294-301')) === 0
        && substr_count($xml, $esc('WorkflowService.php:223-227')) === 0,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("\n%d statements verified; %s\n", count($statements), $fails === 0 ? 'all edits and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass88.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
