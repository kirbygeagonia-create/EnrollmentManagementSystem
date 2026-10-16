<?php

/**
 * Pass 108b — the run-on pass 108 left behind.
 *
 * Pass 108 replaced a clause but not the ` while` that followed it, so the sentence now stacks two
 * concurrent clauses: "…15 of them still in progress in three different colleges **while** the launcher
 * keyed the card to the Guidance office id 4 **which is how**…". One fact per clause, so the second `while`
 * becomes its own sentence. Nothing else in the paragraph changes, and the three measured numbers pass 108
 * wrote are re-checked so this pass cannot quietly edit them away.
 *
 * DRY RUN unless --apply.
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

$fails = 0;

$from = '15 of them still in progress in three different colleges while the launcher keyed the card to the '
    .'Guidance office id 4 — which is how';
$to = '15 of them still in progress in three different colleges. The launcher, meanwhile, keyed the card to '
    .'the Guidance office id 4, and that is how';

$n = substr_count($xml, $from);
if ($n !== 1) {
    fwrite(STDERR, "REFUSE: anchor appears {$n} time(s), expected 1\n");
    exit(1);
}

$at = strpos($xml, $from);
if (substr($xml, $at, strlen($from)) !== $from || str_contains($from, '<')) {
    fwrite(STDERR, "REFUSE: the slice at byte {$at} is not the anchor, or it carries markup\n");
    exit(1);
}

$xml = substr_replace($xml, $to, $at, strlen($from));
printf("replaced at byte %d: %d → %d chars\n", $at, strlen($from), strlen($to));

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the stacked while is gone' => substr_count($xml, $from) === 0,
    'the sentence now ends once and starts again' => substr_count($xml, 'three different colleges. The launcher, meanwhile,') === 1,
    'the measured figures survive the rewrite' => str_contains($xml, 'while 29 rows name office4_head')
        && str_contains($xml, '15 of them still in progress')
        && str_contains($xml, 'Ruled the same day, the boundary those desks work inside'),
    'the old joiner is gone and the new one is there once' => substr_count($xml, 'Guidance office id 4 — which is how') === 0
        && substr_count($xml, 'the Guidance office id 4, and that is how') === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass108b.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
