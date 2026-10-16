<?php

/**
 * Pass 82 — one sentence in pass 80 described a gap that this batch then closed in the
 * deployment notes, so it now describes an older build. Two clauses rewritten; nothing else moves.
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

$edits = [
    [
        'Two findings are reported and not acted on. idrequests.cardPhotoPath is still accepted as free text',
        'Two findings came out of it and neither was quietly fixed. The first is left standing, for a ruling: '
        .'idrequests.cardPhotoPath is still accepted as free text',
    ],
    [
        'student face photos by URL past both authorize() calls, which the deployment notes do not currently mention.',
        'student face photos by URL past both authorize() calls. That one is closed by order rather than by code: '
        .'DEPLOYMENT.md §2 and .env.example now both state that the default disk stays private, and an explicit '
        .'named disk remains available if a deployment ever needs public file serving for something else.',
    ],
];

foreach ($edits as [$from, $to]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== 1) {
        fwrite(STDERR, "REFUSE: needle seen {$count}x, expected 1x: ".substr($from, 0, 70)."\n");
        exit(1);
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    echo 'edit ok  '.substr($from, 0, 64)."…\n";
}

$parseOk = (function () use ($xml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($xml);
    libxml_clear_errors();

    return $r !== false;
})();

$guards = [
    'parses as XML' => $parseOk,
    'no table gained or lost' => substr_count($original, '<w:tbl>') === substr_count($xml, '<w:tbl>'),
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the stale claim is gone' => substr_count($xml, $esc('which the deployment notes do not currently mention')) === 0,
    'the new sentence is in once' => substr_count($xml, $esc('closed by order rather than by code')) === 1,
];

$failed = 0;
foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}

printf("%d guard(s) ok\n", count($guards));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass82.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
