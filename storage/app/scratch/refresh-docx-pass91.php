<?php

/**
 * Pass 91 — C-7's factual half, closed by measurement; the policy half stays open.
 *
 * The document called the skip's rationale "an unverified assumption carried in a comment".
 * It is verified now, and the verified answer is neither of the two the sentence imagined:
 * nothing in the clearance module touches a scholarship table, but the clearance does carry a
 * Scholarship-office requirement line, and every clearance on file has it approved or waived.
 * So the skip rests on an office attesting, not on data being checked — which is a sharper
 * question for §28 C-7 than "is the comment true".
 *
 * The comment in WorkflowService was rewritten inside its own line count, so the only citation
 * that moved is the one naming it: 44-45 was already pointing two lines above the rationale.
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
        'WorkflowService.php:44-45',
        'WorkflowService.php:46-48',
        2,
        'the skip rationale citation',
    ],
    [
        '. The skip is therefore an unverified assumption carried in a comment (§28, C-7).',
        '. Verified 2026-10-11 rather than assumed: ClearanceController, ClearancePolicy and the four clearance'
        .' models — studentclearances, clearanceperiods, clearanceapprovals, clearancerequirements — name no'
        .' scholarship table anywhere, so the rationale was never a data check.'
        .' What the clearance does hold is a Scholarship-office requirement line'
        .' (ProvisionalClearanceRequirements.php:29-39), and measured on live ems all five clearances on file'
        .' carry that line and have it approved or waived — so the skip rests on that office attesting,'
        .' not on any scholarship record being examined. Two further facts belong to it: no shifter or'
        .' transferee clearance exists on file at all, so that half of the population passes by absence'
        .' rather than by evidence (the same shape as G-9), and whether an attestation is enough is'
        .' still §28, C-7’s question, not one this document can answer.',
        1,
        'the C-7 verdict sentence',
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
    printf("edit ok  %-32s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the stale citation is gone' => substr_count($xml, $esc('WorkflowService.php:44-45')) === 0,
    'the new citation is in twice' => substr_count($xml, $esc('WorkflowService.php:46-48')) === 2,
    'the old verdict wording is gone' => substr_count($xml, $esc('unverified assumption carried in a comment')) === 0,
    'the measured facts are in once' => substr_count($xml, $esc('Verified 2026-10-11 rather than assumed')) === 1
        && substr_count($xml, $esc('passes by absence rather than by evidence')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass91.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
