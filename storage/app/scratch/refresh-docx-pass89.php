<?php

/**
 * Pass 89 — the command inventory was stale by three, including one this document itself
 * recorded a day ago.
 *
 * It said "app/Console/Commands holds these four" while the folder held six: pass 86 added
 * ems:uploads:prune to the build and to DEPLOYMENT.md but not to this sentence. This pass
 * lists seven and says what the three operator commands answer, with the spans measured
 * against the working files rather than written from memory.
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

$prose = [
    [
        ' holds these four: ',
        ' holds these seven: ',
        1,
        'the count of commands',
    ],
    [
        'ImportStudents, PrintFidelitySamples',
        'ImportStudents, PrintFidelitySamples, PruneUploadedFiles, AuditRecordStates, Preflight',
        1,
        'the list of command classes',
    ],
    [
        '. Nothing archives or trims ',
        ', the last three there so an operator does not have to write the same query again to ask a question'
        .' the audit already answered. ems:uploads:prune reports uploaded files that no record points at and'
        .' touches nothing without --delete (PruneUploadedFiles.php:33-93). ems:audit:record-states is the one'
        .' this document should have had from the start: ten rules over idrequests, workflowsteps,'
        .' enrollmentworkflow, enrollments, notifications and documentprintlog — a validated request with no'
        .' face photo, a box completed with no officer on it, a request still open under a box its own office'
        .' already signed, a notice addressed to a class that does not exist — each counted, the impossible'
        .' ones failing the run (AuditRecordStates.php:44-138, the signed-box rule at :162-169), while printed'
        .' papers attributable to nobody are noted rather than failed, because that is §28 G-9’s open decision'
        .' and a report tool must not make it. ems:preflight turns DEPLOYMENT.md into a command: environment'
        .' pair, database, unapplied migrations, an unseeded RBAC, the privacy of the documents disk, the'
        .' folders Laravel must write into, a browser for PDFs and the queue connection, each failure naming'
        .' the fix and nothing at all being written (Preflight.php:81-94, the disk rule at :175-198). Nothing'
        .' archives or trims ',
        1,
        'the clause that follows the list',
    ],
];

$fails = 0;
$statements = [];

foreach ($prose as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    $statements[] = $label;
    printf("edit ok  %-38s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the stale count is gone' => substr_count($xml, $esc(' holds these four: ')) === 0,
    'seven is in once' => substr_count($xml, $esc(' holds these seven: ')) === 1,
    'the three new commands are named' => substr_count($xml, $esc('PruneUploadedFiles, AuditRecordStates, Preflight')) === 1
        && substr_count($xml, $esc('ems:audit:record-states is the one')) === 1
        && substr_count($xml, $esc('ems:preflight turns DEPLOYMENT.md into a command')) === 1,
    'the following sentence is intact' => substr_count($xml, $esc('Nothing archives or trims ')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass89.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
