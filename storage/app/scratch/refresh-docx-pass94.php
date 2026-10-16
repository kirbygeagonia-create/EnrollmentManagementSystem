<?php

/**
 * Pass 94 — the sentence pass 93 left as an unmet precondition is now met, and a document that
 * describes a blocker the build has since removed is a document describing an older build.
 *
 * The counts quoted here were read from the run and from the desk afterwards, not written from
 * memory: the seat is enrolled at the ID stage, the pipeline's open-seat counter reads 1, the
 * queue then lists two enrollments, and the whole suite passed on the live database with the new
 * student in it.
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
        'the demo dataset parks exactly one enrollment at that stage — already carrying its request —'
        .' so §18’s create step needs a second record advanced to the ID box before an office can walk it.',
        'the demo dataset parked exactly one enrollment at that stage and it already carried its request, so the'
        .' scripted step had no record to be performed on. Closed the same day, in the data rather than in the'
        .' policy: the demo pipeline now advances DEMO-2026-026 (Alcantara, Nena) to the ID box and deliberately'
        .' writes no request for her, because the seat exists to be acted on by the office performing the step.'
        .' Each run prints how many open seats are left — 1 measured on this install — and walking the desk'
        .' afterwards showed two enrollments in the queue, hers reading “None”, with the intake form offering the'
        .' reason, emergency-contact and blood-type fields the step asks for. Deleting the contradiction (idrequests 53)'
        .' and seating an open record are two halves of one decision: a demo dataset cannot demonstrate a workflow'
        .' stage it never leaves open.',
        1,
        'the create-step precondition, now met',
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
    printf("edit ok  %-40s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the stale precondition is gone' => substr_count($xml, $esc('needs a second record advanced to the ID box before an office can walk it')) === 0,
    'the seat is named' => substr_count($xml, $esc('DEMO-2026-026 (Alcantara, Nena)')) === 1,
    'the open-seat counter is recorded' => substr_count($xml, $esc('Each run prints how many open seats are left')) === 1,
    'the following bullet survives' => substr_count($xml, 'No retention or purge.') === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass94.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
