<?php

/**
 * Pass 96 — the decision sheet was described as "one page per question" before its own layout was
 * measured. It is one 152-line sheet with the questions set under the four papers they belong to,
 * so the sentence describing it was the kind of claim that reads well and is not true.
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
        'one page per question, what the physical form says set against what the template prints,'
        .' a box to tick and an initial, and a closing table of what each answer costs to carry out.',
        'the nine questions set out under the four papers they belong to, each showing what the physical'
        .' form says against what the template prints, with a box to tick and an initial, and a closing'
        .' table of what each answer costs to carry out.',
        1,
        'the sheet described as it is laid out, not as it was imagined',
    ],
];

$fails = 0;
$verified = 0;

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-56s  %dx\n", $label, $expect);
    $verified++;
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the wrong layout claim is gone' => substr_count($xml, $esc('one page per question')) === 0,
    'the new phrasing is in once' => substr_count($xml, $esc('the nine questions set out under the four papers')) === 1,
    'the sheet is still named once' => substr_count($xml, $esc('Registrar-Print-Decision-Sheet-2026-10-11.md')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("\n%d statements verified; %s\n", $verified, $fails === 0 ? 'all edits and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass96.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
