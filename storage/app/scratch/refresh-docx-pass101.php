<?php

/**
 * Pass 101 — the citation this batch's own edit invalidated.
 *
 * Today's batch grew AssertNoSilentSkips.php: the walkthrough's allowlist entry gained two lines of reason,
 * so the constant spans 39..50 instead of 39..48 and handle() moved from 50..99 to 52..101. Both ends were
 * opened and read, not computed: :52 is `public function handle(): int`, :101 its closing brace, :39 is
 * `private const ALLOWED = [`, :50 the `];`. This is the drift AGENTS.md warns about — one offset cannot
 * carry two edits, and a citation is re-measured in the same batch that moves the file.
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

// Re-measure against the real file rather than trusting this script's own prose.
$source = file_get_contents($root.'/app/Console/Commands/AssertNoSilentSkips.php');
$lines = explode("\n", $source);
$checks = [
    39 => 'private const ALLOWED = [',
    50 => '];',
    52 => 'public function handle(): int',
    101 => '}',
];
foreach ($checks as $n => $expect) {
    if (! str_contains(trim($lines[$n - 1]), $expect)) {
        fwrite(STDERR, sprintf("REFUSE: %s line %d is [%s], not [%s]\n", 'AssertNoSilentSkips.php', $n, trim($lines[$n - 1]), $expect));
        exit(1);
    }
    printf("verified  AssertNoSilentSkips.php:%d = %s\n", $n, trim($lines[$n - 1]));
}

$edits = [
    [
        '(AssertNoSilentSkips.php:50-99, the allowlist at :39-48)',
        '(AssertNoSilentSkips.php:52-101, the allowlist at :39-50)',
        1,
        'the guard citation, re-measured after the allowlist grew',
    ],
];

$fails = 0;
foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-52s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the old span is gone everywhere' => substr_count($xml, $esc('AssertNoSilentSkips.php:50-99')) === 0
        && substr_count($xml, $esc('the allowlist at :39-48')) === 0,
    'the new span is in once' => substr_count($xml, $esc('(AssertNoSilentSkips.php:52-101, the allowlist at :39-50)')) === 1,
    'no other guard citation moved' => substr_count($xml, $esc('Preflight.php:81-94, the disk rule at :175-198')) === 1
        && substr_count($xml, $esc('PruneUploadedFiles.php:33-93')) === 1,
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

echo $fails === 0 ? "\nall edits and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass101.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
