<?php

/**
 * Pass 9 — repair my own pass-8 slip. That edit used a prefix as its needle, so the rest
 * of the sentence it replaced stayed behind and the documentNumber row ended up saying
 * two contradictory things. A prefix is not a target: this pass removes the dangling tail
 * and corrects one cross-reference (the print remediation table is §25.10, not §26).
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx9-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$pairs = [
    // The leftover second half of the sentence pass 8 should have replaced wholesale.
    [' it sequences per (enrollmentId, documentType) under lockForUpdate() and the unique index uq_documentprintlog_issue rejects a collision, so the number means "the nth time this document was issued" and cannot mean two things at once.',
        '', 1, 'drop the dangling tail'],
    ['for the period’s term (§26)', 'for the period’s term (§25.10)', 1, 'slip audit row cites §25.10'],
];

$fails = 0;
foreach ($pairs as [$needle, $to, $want, $label]) {
    $n = substr_count($xml, $needle);
    if ($n !== $want) {
        echo "FAIL {$label}: found {$n}x, want {$want}x\n";
        $fails++;
        continue;
    }
    $xml = str_replace($needle, $to, $xml);
    echo "ok   {$label}\n";
}

if ($fails > 0) {
    fwrite(STDERR, "not writing\n");
    exit(1);
}
if (! $apply) {
    echo "dry run — pass --apply to write\n";
    exit(0);
}
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED\n";
