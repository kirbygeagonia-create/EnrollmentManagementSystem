<?php

/**
 * Pass 102 — the same id trap, one file over, where it mislabelled money instead of skipping a leg.
 *
 * Pass 100 recorded why a name-keyed seeder's ids cannot be assumed. The demo pipeline had the assumption
 * in five places at once, and on a fresh install the consequence was not an abort but a fee sheet whose
 * tuition line was attached to the Clearance Slip Replacement — accepted by the foreign key, wrong on the
 * paper. Both halves are stated, including the part this batch did not fix.
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
        'the mercy of the counter. ',
        'the mercy of the counter. The same assumption sat one file over, in more places at once: the demo '.
        'pipeline wrote fee type ids 1 through 4, subject 5 as IT101, room 1, and a hand-kept map from office '.
        'to staff user, all of which are right only where that database’s counters happen to agree. Measured '.
        'on a freshly migrated and seeded install, fee type 1 there is the Clearance Slip Replacement, so '.
        'every demo charge attached to the wrong paper and the foreign key accepted the row without a word — '.
        'a silent mislabel, worse than the abort the same file hits on a database whose reference rows were '.
        'rebuilt, where those ids are absent and the run dies on the schedule insert. Those lookups now go '.
        'through the names (subjectCode IT101, roomName, feeName, and the seeded officeN_head accounts), and '.
        'all fifteen were checked to return the ids the literals used, so the demo data on the development '.
        'machine did not move. What this batch did not fix is stated where it belongs: the tool still '.
        'presumes its named demo students already exist, aborting on DEMO-2026-004 when they do not, and it '.
        'prints that failure while still exiting 0 — so anything gating on its exit code reads a pass. ',
        1,
        'the demo pipeline’s id assumptions, the silent fee mislabel, and what is still open',
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
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the passage still hands off' => substr_count($xml, $esc('reads a pass. Nothing archives or trims')) === 1,
    'the new claims are present once' => substr_count($xml, $esc('fee type 1 there is the Clearance Slip Replacement')) === 1
        && substr_count($xml, $esc('all fifteen were checked to return the ids the literals used')) === 1
        && substr_count($xml, $esc('aborting on DEMO-2026-004')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass102.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
