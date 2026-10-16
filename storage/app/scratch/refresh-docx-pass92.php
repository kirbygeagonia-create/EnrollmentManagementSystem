<?php

/**
 * Pass 92 — an integrity row that still asserted a present-tense reconciliation to numbers the
 * live tables no longer hold.
 *
 * The historical registers elsewhere in §28 quote 95 and 94 permissions because those were the
 * counts on the days their rulings landed; those are left alone — rewriting a dated record would
 * falsify it. This cell is different: it claims the live count reconciles *now*, so it has to
 * carry the measurement taken now. It also records the ambiguity this check exposed, because a
 * number quoted as "modules" meant two different things in the same document.
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
        'Unique pair. 315 rows — the live count reconciles exactly with the §22.3 matrix, which is generated from the same seeder (94 permissions, 17 roles)',
        'Unique pair. Measured 2026-10-11 on live ems: 298 grant rows, 93 permissions, 17 roles, and the §22.3 '
        .'matrix is generated from the same seeder, so the two agree by construction rather than by transcription. '
        .'One word needs care: the seeder organises the catalogue into 14 module groups while the permission names '
        .'carry 17 distinct prefixes (audit, print and settings live inside other groups’ arrays), so a figure '
        .'quoted as “modules” has to say which of the two it means. Earlier counts of 95 and 94 permissions in '
        .'this document are dated records of the days their rulings landed, and are left as written.',
        1,
        'the RBAC reconciliation cell',
    ],
];

$fails = 0;
$statements = [];

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    $statements[] = $label;
    printf("edit ok  %-32s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table cell gained or lost' => substr_count($original, '<w:tc>') === substr_count($xml, '<w:tc>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the stale count is gone' => substr_count($xml, $esc('315 rows')) === 0,
    'the new measurement is in once' => substr_count($xml, $esc('298 grant rows, 93 permissions, 17 roles')) === 1,
    'the module ambiguity is stated' => substr_count($xml, $esc('14 module groups while the permission names')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass92.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
