<?php

/**
 * Pass 103 — the citation this batch shifted, and the launcher card it proved wrong.
 *
 * Adding two shared flags to HandleInertiaRequests moved the flash block from 86-91 to 94-100, and the
 * document cites that span three times: an audit register row, the §28 row for the `info` key, and the
 * remediation table. Each was re-measured by opening the file and reading which lines the four keys sit on
 * now, not by arithmetic. The second edit records the card this batch found: the Dean was advertised a
 * Blocking screen the policy refuses, which is the same rule lane 4 already applied to Campus Clearance.
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

// Re-measure the middleware span in the real file before asserting the document's citation.
$mw = explode("\n", file_get_contents($root.'/app/Http/Middleware/HandleInertiaRequests.php'));
$marks = [];
foreach ($mw as $no => $line) {
    if (preg_match("/^\s+'(flash|success|warning|error|info)' =>/", $line, $m) === 1) {
        $marks[] = ($no + 1).'='.$m[1];
    }
}
echo 'HandleInertiaRequests flash block lines: '.implode(' ', $marks)."\n";
$first = (int) explode('=', $marks[0])[0];
$last = (int) explode('=', $marks[count($marks) - 1])[0];
if ($first !== 94 || $last !== 100) {
    fwrite(STDERR, "REFUSE: the flash block measures {$first}-{$last}, not the 94-100 this pass writes\n");
    exit(1);
}

$edits = [
    [
        'HandleInertiaRequests.php:86-91',
        'HandleInertiaRequests.php:94-100',
        3,
        'the three citations to the flash block, re-measured after two flags were added above it',
    ],
    [
        'two dropdowns stopped hard-coding what their enum already decides.',
        'two dropdowns stopped hard-coding what their enum already decides. The same rule had one card left to '
        .'catch, found on 2026-10-12: the launcher granted the Dean the Blocking & Timetables card by route '
        .'name, while the Dean role does not hold block.view — so the chrome advertised a screen '
        .'BlockingPolicy::viewAny refuses, and dean_academic is the only dean account the seeder makes. '
        .'Diffed across every seeded account before the change, exactly one card for one account moved, '
        .'because the rights behind all the others already reached their screens. Those two cards now read '
        .'can.evaluationDesk and can.blockingDesk, shared from the same rights the desk routes ask, and '
        .'LauncherCardVisibilityTest pins each flag beside the refusal or the page behind it.',
        1,
        'the dean’s dead-end card, recorded in the lane-4 register row',
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
    printf("edit ok  %-56s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the stale span is gone everywhere' => substr_count($xml, $esc('HandleInertiaRequests.php:86-91')) === 0,
    'the new span is in three times' => substr_count($xml, $esc('HandleInertiaRequests.php:94-100')) === 3,
    'the new claims are present once' => substr_count($xml, $esc('can.evaluationDesk and can.blockingDesk')) === 1
        && substr_count($xml, $esc('exactly one card for one account moved')) === 1,
    'the sentence still hands off to the next' => substr_count($xml, $esc('the page behind it. Ten tests cover the fixes')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass103.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
