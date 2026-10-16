<?php

/**
 * Pass 117 — five citations in the authority docx point at the wrong lines, found by the meaning sweep.
 *
 * `sweep-docx-citations.php` cleared all 549 of these: every one names a real file, ends inside it, and lands
 * on a non-blank line. That is exactly why these five survived — a range that stayed in bounds while the
 * construct it labels moved is invisible to a line check. Each fix is measured against the file, not against
 * arithmetic: the guard opens the NEW range and insists the named construct is really there, then checks the
 * OLD line holds something else, and prints both so the pass record carries the contrast rather than a number.
 *
 *   AdmissionController.php:121 -> :125             `Rule::enum(ApplicantType::class)` is on 125; 121 is the
 *                                                   username rule, one of five look-alike lines
 *   PrintService.php :105       -> :115             `recordIssue()` is declared on 115; 105 sits in another
 *                                                   method's doc-comment about row locks
 *   PrintService.php :244       -> :254             `printClassCard()` is declared on 254; 244 is the
 *                                                   `->values()` inside cardSubjects()
 *   DevReferenceDataSeeder.php :734-740 -> :748-756 the loop that builds each line from NAMES is 748-756;
 *                                                   734-740 is the PROVISIONAL comment above it
 *   WorkflowService.php :150-174 -> :289-305        signStepByOffice() spans 289-305; 150-174 is inside
 *                                                   recordFellBackBelowTheBox()
 *
 * Six other flags the sweep printed are NOT touched, and are recorded in the sweep's header as its known
 * noise: `OfficeId::Clinic` on a line whose whole point is that it says `11`; `Payments::held()` landing on
 * the accounting range a clause opened with; `ChromiumLocator::locate` paired by prose order with the first
 * of two ranges; a migration cite glossed by a seeder constant fifty characters later; and two labels whose
 * nearest citation sits on the wrong side of a comma.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$zip = new ZipArchive;
$zip->open($src) !== true && exit(1);
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

/**
 * Each edit: [anchor in the XML, replacement, file, first line of the new range, last line of it,
 * strings that new span must contain, the line the old citation pointed at].
 */
$edits = [
    ['AdmissionController.php:121', 'AdmissionController.php:125',
        'app/Http/Controllers/Admission/AdmissionController.php', 125, 125,
        ['Rule::enum(ApplicantType::class)'], 121],
    ['recordIssue() at :105', 'recordIssue() at :115',
        'app/Services/PrintService.php', 115, 115,
        ['function recordIssue'], 105],
    ['printClassCard() at :244', 'printClassCard() at :254',
        'app/Services/PrintService.php', 254, 254,
        ['function printClassCard'], 244],
    [':734-740 builds', ':748-756 builds',
        'database/seeders/DevReferenceDataSeeder.php', 748, 756,
        ['ProvisionalClearanceRequirements::NAMES', 'foreach', 'save()'], 734],
    [':150-174</w:t>', ':289-305</w:t>',
        'app/Services/WorkflowService.php', 289, 305,
        ['function signStepByOffice', 'where(\'officeId\', $officeId)'], 150],
];

$fails = 0;
$report = [];

foreach ($edits as [$from, $to, $file, $first, $last, $mustContain, $wasAt]) {
    $count = substr_count($xml, $from);
    if ($count !== 1) {
        fwrite(STDERR, "REFUSE: `{$from}` appears {$count} time(s), expected exactly 1\n");
        $fails++;

        continue;
    }

    $lines = file($root.'/'.$file);
    if ($last > count($lines) || $wasAt > count($lines)) {
        fwrite(STDERR, "REFUSE: {$file} has ".count($lines)." lines\n");
        $fails++;

        continue;
    }

    $span = implode(' ', array_map('trim', array_slice($lines, $first - 1, $last - $first + 1)));
    foreach ($mustContain as $needle) {
        if (! str_contains($span, $needle)) {
            fwrite(STDERR, "REFUSE: new range {$file}:{$first}-{$last} does not contain `{$needle}`\n");
            $fails++;
        }
    }

    // The old line must belong to something ELSE, or this edit is not a fix — it is a reshuffle.
    $oldLine = trim($lines[$wasAt - 1]);
    foreach ($mustContain as $needle) {
        if (str_contains($oldLine, $needle)) {
            fwrite(STDERR, "REFUSE: the OLD line {$file}:{$wasAt} already holds `{$needle}`, so nothing moved\n");
            $fails++;
        }
    }

    $xml = str_replace($from, $to, $xml);
    $report[] = sprintf('%-26s -> %-24s was %s:%d  %s',
        $from, $to, basename($file), $wasAt, substr(str_replace('"', '', $oldLine), 0, 60));
}

$guards = [
    'all five anchors consumed' => substr_count($xml, 'AdmissionController.php:121') === 0
        && substr_count($xml, 'recordIssue() at :105') === 0
        && substr_count($xml, 'printClassCard() at :244') === 0
        && substr_count($xml, ':734-740') === 0
        && substr_count($xml, ':150-174') === 0,
    'all five replacements landed once each' => substr_count($xml, 'AdmissionController.php:125') === 1
        && substr_count($xml, 'recordIssue() at :115') === 1
        && substr_count($xml, 'printClassCard() at :254') === 1
        && substr_count($xml, ':748-756 builds') === 1
        && substr_count($xml, ':289-305</w:t>') === 1,
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the document still parses' => simplexml_load_string($xml) !== false,
    // Pass 116 wrote one citation into this same document hours ago; a later pass must not disturb it.
    'the disk rule citation is still :344-367' => substr_count($xml, 'the disk rule at :344-367') === 1,
];

foreach ($guards as $name => $ok) {
    $ok ? printf("guard ok   %s\n", $name) : (fwrite(STDERR, "REFUSE guard: {$name}\n") + $fails++);
}

echo "\n";
foreach ($report as $r) {
    echo '  '.$r."\n";
}

if ($fails > 0) {
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-15-pass117.docx');
$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();
printf("WROTE (%+d bytes)\n", strlen($xml) - strlen($original));
