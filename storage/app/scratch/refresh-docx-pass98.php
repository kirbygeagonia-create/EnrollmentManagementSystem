<?php

/**
 * Pass 98 — the CI measurement, and the eighth operator command.
 *
 * This document keeps its command inventory as a hand-written list, and that list has gone stale before
 * (pass 89-90 found it three commands short), so it is corrected in the same breath as the code. The
 * sentence about what CI proves is new because the answer was measured, not assumed: both backend jobs
 * print five skipped and call the run green, and one of those five would become the browser check the
 * moment the runner image stops shipping Chrome.
 *
 * The needles are run-shaped, not sentence-shaped: this paragraph splits its monospace command names
 * into their own runs, so a needle spanning two runs cannot match and the guards below are what stop a
 * silent no-op.
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
        ' holds these seven: ',
        ' holds these eight: ',
        1,
        'the inventory count',
    ],
    [
        'PruneUploadedFiles, AuditRecordStates, Preflight',
        'PruneUploadedFiles, AuditRecordStates, AssertNoSilentSkips, Preflight',
        1,
        'the eighth command named',
    ],
    [
        '. The last three are there because',
        '. The last four are there because',
        1,
        'the operator-command count',
    ],
    [
        '(Preflight.php:81-94, the disk rule at :175-198). Nothing archives or trims ',
        '(Preflight.php:81-94, the disk rule at :175-198). ems:ci:no-silent-skips reads the JUnit log the'
        .' test step already writes and refuses a skip this project has not agreed to, because a check that'
        .' did not run cannot vouch for anything: measured on commit 2986427 both backend jobs print five'
        .' skipped and 660 passed, four of them the end-to-end legs naming a live ems database the service'
        .' container never creates, the fifth the Preflight row that needs a prints folder which gitignore'
        .' keeps out of every checkout. That suite is green there partly because the runner image ships a'
        .' browser the locator can find, and the check that proves it is written to skip rather than fail'
        .' when none is found — so a leaner image would have subtracted proof without one line of red.'
        .' ci.yml is therefore pinned to ubuntu-24.04, which ubuntu-latest leaves on 2026-10-19, with the'
        .' guard running at the end of each backend job (AssertNoSilentSkips.php:45-96, the allowlist at'
        .' :34-43), and DEPLOYMENT.md §7 now states what CI does not prove: the end-to-end walkthrough'
        .' never runs on it. Nothing archives or trims ',
        1,
        'what CI proves, and the pin that keeps it honest',
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
    printf("edit ok  %-44s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the old inventory is gone' => substr_count($xml, $esc('holds these seven')) === 0
        && substr_count($xml, $esc('. The last three are there because')) === 0
        && substr_count($xml, $esc('AuditRecordStates, Preflight')) === 0,
    'the new text is in once each' => substr_count($xml, $esc('holds these eight')) === 1
        && substr_count($xml, $esc('AuditRecordStates, AssertNoSilentSkips, Preflight')) === 1
        && substr_count($xml, $esc('. The last four are there because')) === 1
        && substr_count($xml, $esc('AssertNoSilentSkips.php:45-96, the allowlist at')) === 1
        && substr_count($xml, $esc('pinned to ubuntu-24.04')) === 1,
    'the neighbours still join' => substr_count($xml, $esc('the end-to-end walkthrough never runs on it. Nothing archives or trims')) === 1
        // The command names live in their own monospace run, so "holds these eight: " and
        // "BenchmarkHeavyQueries" are never adjacent bytes — this asserts the run that was rewritten.
        && substr_count($xml, $esc(' holds these eight: ')) === 1
        && substr_count($xml, $esc('BenchmarkHeavyQueries')) === substr_count($original, 'BenchmarkHeavyQueries'),
    'no stray markdown left the prose' => substr_count($xml, '`') === substr_count($original, '`'),
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass98.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
