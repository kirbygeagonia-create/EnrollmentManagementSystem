<?php

/**
 * Pass 99 — the walkthrough now has a MySQL step, and the sentence that said it never would.
 *
 * Pass 98 wrote "CI does not prove the end-to-end walkthrough". That was true that morning and false by
 * the evening, which is exactly the drift this document exists to catch, so it is corrected in the same
 * batch as the change. Every number here was measured, not inferred: the exit code of the step without
 * --fail-on-skipped, the reference rows a seeded database supplies, and the method spans inside the guard
 * command after it grew multi-log support.
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
        'reads the JUnit log the test step already writes',
        'reads the JUnit log or logs the test steps already write',
        1,
        'the guard now reads two logs',
    ],
    [
        '(AssertNoSilentSkips.php:45-96, the allowlist at :34-43), and DEPLOYMENT.md §7 now states what CI'
        .' does not prove: the end-to-end walkthrough never runs on it.',
        '(AssertNoSilentSkips.php:50-99, the allowlist at :39-48), and DEPLOYMENT.md §7 states what each step'
        .' proves. Measured the same day, the walkthrough legs got a MySQL step of their own: the suite runs'
        .' first, then migrate and the two seeders, then phpunit tests/Feature/E2E under --fail-on-skipped.'
        .' The order is forced — those legs read reference rows migrations never create (154 subjects, 16'
        .' curriculums, 9 clearance requirements and the seeded roles), and seeding before the suite would'
        .' have been undone by RefreshDatabase migrating fresh. A leg takes its database from'
        .' E2E_DB_DATABASE and still defaults to the developer’s live ems, and it skips by naming the table'
        .' that is missing or empty rather than failing four desks into a pipeline on a null row. Without'
        .' that flag the step reported four skipped and exited 0, measured; with it, the same unseeded'
        .' database exits 1 — the same subtraction of proof, in a new place.',
        1,
        'the walkthrough on MySQL, with the exit codes measured both ways',
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
    printf("edit ok  %-52s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stale claim survives' => substr_count($xml, $esc('the end-to-end walkthrough never runs on it')) === 0
        && substr_count($xml, $esc('AssertNoSilentSkips.php:45-96')) === 0
        && substr_count($xml, $esc('reads the JUnit log the test step already writes')) === 0,
    'the new text is in once each' => substr_count($xml, $esc('AssertNoSilentSkips.php:50-99, the allowlist at :39-48')) === 1
        && substr_count($xml, $esc('under --fail-on-skipped')) === 1
        && substr_count($xml, $esc('reads the JUnit log or logs the test steps already write')) === 1
        && substr_count($xml, $esc('the same unseeded database exits 1')) === 1,
    'the sentence still hands off to the next' => substr_count($xml, $esc('the same subtraction of proof, in a new place. Nothing archives or trims')) === 1,
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass99.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
