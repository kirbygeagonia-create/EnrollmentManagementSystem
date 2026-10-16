<?php

/**
 * Pass 97 — the audit's own instrument, corrected by the measurement that generalized the ID walk.
 *
 * Row 15.1 was found unperformable by eye. This pass exists because the same question was asked of
 * every record the walkthrough names, read-only against live ems: four rows described records that no
 * longer mean what the script says, and one whole class of state nobody had thought to count — a box
 * signed without the act that signs it. The demo data was repaired where a desk is told to walk,
 * left alone where it is history, and the command that reports the difference now says so.
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
        'ten rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, notifications and'
        .' documentprintlog — a validated request with no face photo, a box completed with no officer on it,'
        .' a request still open under a box its own office already signed, a notice addressed to a class'
        .' that does not exist — each counted, the impossible ones failing the run'
        .' (AuditRecordStates.php:44-138, the signed-box rule at :162-169), while printed papers attributable'
        .' to nobody are noted rather than failed, because that is §28 G-9’s open decision and a report'
        .' tool must not make it.',
        'twelve rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords,'
        .' notifications and documentprintlog — a validated request with no face photo, a box completed with no'
        .' officer on it, a request still open under a box its own office already signed, a notice addressed to'
        .' a class that does not exist, a Clinic box signed with no health assessment behind it, an ID box signed'
        .' with no validated request behind it — each counted, the impossible ones failing the run'
        .' (AuditRecordStates.php:44-151, the two box rules at :263-280), while three are noted rather than'
        .' failed because each counts a state somebody has already ruled to keep: printed papers attributable to'
        .' nobody (§28 G-9), the past-term records the demo pipeline had walked through the Clinic and ID boxes'
        .' before it wrote the acts that sign them, and enrollment 53, whose contradictory request row was'
        .' deleted on 2026-10-11 with its signature left standing. A report tool counts those and leaves them.',
        1,
        'the audit command now twelve rules, three of them notes',
    ],
    [
        'the demo dataset now advances a second enrollment to the ID box with no request so that the'
        .' create step can still be walked.',
        'the demo dataset now advances a second enrollment to the ID box with no request so that the'
        .' create step can still be walked. A desk walked it on 2026-10-12 — audit row 75504 records the ID head'
        .' opening that request from ::1 — which is the seat doing its job rather than a gap: the walkthrough now'
        .' says the row has been used, its new §2.4a allows the pipeline’s matching warning line, and the pending'
        .' request left behind is what rows 15.2, 15.3, 15R.5 and 15R.6 are performed on. Asking the same question'
        .' of every record the script names found the record behind a signature missing — nine enrollments sat with'
        .' a completed Clinic box and no health assessment, the state §14R.2 says no desk can produce — so the'
        .' pipeline writes the assessment wherever it signs that box (one helper, three seats: the two live ones'
        .' and Danica’s literal folded into it), while seven past-term rows keep their signature without an'
        .' invented clinical history and are reported as a note. The script was rewritten to the data rather than'
        .' the reverse: Kevin Santos is the finished example instead of the missing-record case, §14.1 moved to'
        .' Patricia Diaz, §13.3 stopped naming her as unseated when the pipeline seats her in BSIT 1-A, and'
        .' DEPLOYMENT.md §8 no longer says the command has ten rules and one note.',
        1,
        'the seat used by a desk, the box signed without its act, and the rows rewritten to the data',
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
    printf("edit ok  %-58s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stale count survives' => substr_count($xml, $esc('ten rules over idrequests')) === 0
        && substr_count($xml, $esc('AuditRecordStates.php:44-138')) === 0
        && substr_count($xml, $esc('the signed-box rule at :162-169')) === 0
        && substr_count($xml, $esc('are noted rather than failed, because that is §28 G-9’s open decision')) === 0,
    'the new text is in once each' => substr_count($xml, $esc('twelve rules over idrequests')) === 1
        && substr_count($xml, $esc('AuditRecordStates.php:44-151, the two box rules at :263-280')) === 1
        && substr_count($xml, $esc('audit row 75504 records the ID head opening that request from ::1')) === 1
        && substr_count($xml, $esc('one helper, three seats')) === 1,
    'the neighbouring sentences still join' => substr_count($xml, $esc('A report tool counts those and leaves them.'
        .' ems:preflight turns DEPLOYMENT.md')) === 1
        && substr_count($xml, $esc('create step can still be walked. A desk walked it on 2026-10-12')) === 1,
    'nothing after the second edit lost its tail' => substr_count($xml, $esc('The ID photo flow was then driven in a real browser')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass97.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
