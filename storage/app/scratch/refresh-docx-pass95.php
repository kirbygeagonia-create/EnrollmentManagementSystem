<?php

/**
 * Pass 95 — three sentences in the session narrative that the last day's work made untrue.
 *
 * None of these were caught by a checker. They were caught by re-reading the document against the
 * files it describes: the doubled weekday has been fixed, so a paragraph listing it as an open
 * question is now describing an older build; the question count moved with it; and the claim that
 * an ID workflow box was completed with nobody on it was disproved the moment the row was read from
 * live ems — it carried a signer and a date all along. Chasing that misreport is what surfaced the
 * defect that really was there, which is the argument for correcting the sentence rather than
 * leaving it.
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
        'ten questions left for the Registrar to answer because they concern the paper, not the code —'
        .' among them that our class card prints the day twice, that the subject load',
        'nine questions left for the Registrar to answer because they concern the paper, not the code'
        .' (the tenth of those ten — our class card printing its weekday twice — was a defect rather than'
        .' a preference, and is closed) — among them that the subject load',
        1,
        'the open-question count and the fixed doubled day',
    ],
    [
        'Its workflow box is separately recorded as completed with no validator, which no desk action'
        .' could produce either, and that is left for the owner rather than quietly repaired, because'
        .' un-signing a box rewrites an audit trail.',
        'Its workflow box is separately recorded in this document as completed with no validator — and that'
        .' claim was wrong, withdrawn on 2026-10-11 the day the row was read from live ems, where the ID box'
        .' carries signedBy 11 and a date of 2026-09-13: no signature was missing and none had to be'
        .' rewritten. Pursuing the misreport is what surfaced the defect actually present, and it is now'
        .' ruled and closed — a box that is signed refuses a second signature, and only a record whose own'
        .' history shows it fell back below that box may be certified again, the fall-back proved from'
        .' enrollmentstatushistory rather than the live status because a paying desk writes the status before'
        .' it signs. The request that sat pending under the signed box was deleted rather than turned into a'
        .' new state, and since one enrollment holds one request for its life, the demo dataset now advances a'
        .' second enrollment to the ID box with no request so that the create step can still be walked.',
        1,
        'the withdrawn no-validator claim, and what replaced it',
    ],
    [
        'and that the block schedule is a meeting list where the wall chart is a weekly grid.'
        .' The real-data migration also gained its first tool',
        'and that the block schedule is a meeting list where the wall chart is a weekly grid.'
        .' The nine were put to the office on 2026-10-11 as'
        .' Documentation/Registrar-Print-Decision-Sheet-2026-10-11.md: one page per question, what the'
        .' physical form says set against what the template prints, a box to tick and an initial, and a'
        .' closing table of what each answer costs to carry out. The sheet numbers a tenth row that is not a'
        .' question but a request — the blank Enrollment Certificate and Enrollment Form, without which those'
        .' two papers cannot be compared at all — and it changes nothing in the software in advance of the'
        .' office’s hand.'
        .' The real-data migration also gained its first tool',
        1,
        'the decision sheet, recorded in the print-fidelity narrative',
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
    printf("edit ok  %-56s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stale claim survives' => substr_count($xml, $esc('ten questions left for the Registrar')) === 0
        && substr_count($xml, $esc('our class card prints the day twice')) === 0
        && substr_count($xml, $esc('completed with no validator, which no desk action')) === 0,
    'the corrections are in once' => substr_count($xml, $esc('nine questions left for the Registrar')) === 1
        && substr_count($xml, $esc('Registrar-Print-Decision-Sheet-2026-10-11.md')) === 1
        && substr_count($xml, $esc('that claim was wrong, withdrawn on 2026-10-11')) === 1
        && substr_count($xml, $esc('the tenth of those ten')) === 1
        && substr_count($xml, $esc('in advance of the office’s hand')) === 1,
    'the print paragraph still reaches its next sentence' => substr_count(
        $xml,
        $esc('office’s hand. The real-data migration also gained its first tool')
    ) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass95.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
