<?php

/**
 * Pass 104 — a new preflight rule, and the two citations into that file the rule moved.
 *
 * ems:preflight gained a clock check because the development box writes two different strings for one
 * instant: PHP stamps 2026-10-13 05:32 and MySQL's NOW() stamps 2026-10-12 18:32, an 11.0-hour split on the
 * two DATETIME columns that carry a database default. Adding the rule shifted checks() and
 * uploadDiskIsPrivate(), so both spans were re-opened and read rather than offset by arithmetic.
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

$lines = explode("\n", file_get_contents($root.'/app/Console/Commands/Preflight.php'));
$expect = [
    82 => 'private function checks(): array',
    96 => '}',
    224 => 'private function uploadDiskIsPrivate(): array',
    247 => '}',
];
$badSourceLines = 0;
foreach ($expect as $n => $text) {
    if (trim($lines[$n - 1]) !== $text) {
        fwrite(STDERR, sprintf("REFUSE: Preflight.php line %d is [%s], expected [%s]\n", $n, trim($lines[$n - 1]), $text));
        $badSourceLines++;

        continue;
    }
    printf("verified  Preflight.php:%d = %s\n", $n, trim($lines[$n - 1]));
}

if ($badSourceLines > 0) {
    fwrite(STDERR, "ABORT: {$badSourceLines} cited line(s) do not say what the document claims — nothing was written\n");
    exit(1);
}

$edits = [
    [
        'Preflight.php:81-94, the disk rule at :175-198',
        'Preflight.php:82-96, the disk rule at :224-247',
        1,
        'both spans into Preflight.php, re-measured after the clock rule was added',
    ],
    [
        'ems:preflight turns DEPLOYMENT.md into a command: environment pair, database, unapplied migrations, an unseeded RBAC, the privacy of the documents disk, the folders Laravel must write into, a browser for PDFs and the queue connection,',
        'ems:preflight turns DEPLOYMENT.md into a command: environment pair, database, whether PHP and the database write the same string into a DATETIME (the two disagreed by 11.0 hours on the development box, which is what the two columns carrying a MySQL CURRENT_TIMESTAMP default would silently inherit), unapplied migrations, an unseeded RBAC, the privacy of the documents disk, the folders Laravel must write into, a browser for PDFs and the queue connection,',
        1,
        'the new clock rule joins the list of what preflight answers',
    ],
];

$fails = 0;
foreach ($edits as [$from, $to, $expect_count, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect_count) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect_count));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-56s  %dx\n", $label, $expect_count);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the old spans are gone' => substr_count($xml, $esc('Preflight.php:81-94')) === 0 && substr_count($xml, $esc('disk rule at :175-198')) === 0,
    'the new spans are in once' => substr_count($xml, $esc('Preflight.php:82-96, the disk rule at :224-247')) === 1,
    'the enumeration still ends the same way' => substr_count($xml, $esc('a browser for PDFs and the queue connection, each failure naming the fix')) === 1,
    'the new claim is present once' => substr_count($xml, $esc('disagreed by 11.0 hours on the development box')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass104.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
