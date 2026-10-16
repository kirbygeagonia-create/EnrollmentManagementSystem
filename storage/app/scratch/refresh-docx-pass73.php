<?php

/**
 * Pass 73 — AssessmentController.php lost one line (the `feeTypes` payload Assessment/Show never
 * read), so every citation of a line below it now points one line too far down.
 *
 * Shifted by arithmetic, then verified by MEANING: `probe-docx-cite-method.php` re-reads each cite
 * after the write and prints the method the new line sits in. Baseline recorded before the deletion
 * (finalize 410-430, compute 162-179 / 126-138 / 120-123 / 136-141 / 162-166, index 45, the
 * applyScholarship and withdrawScholarship ranges) must be unchanged, because the file only shrank
 * above them. A citation whose own range spans the deleted line is refused rather than guessed at.
 */

$src = 'Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$deletedAt = 102;

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('#AssessmentController\.php:(\d{1,4})(?:-(\d{1,4}))?#', $xml, $m, PREG_SET_ORDER);
$tokens = [];
foreach ($m as $set) {
    $a = (int) $set[1];
    $b = isset($set[2]) && $set[2] !== '' ? (int) $set[2] : null;
    if ($b === null && $a === $deletedAt) {
        fwrite(STDERR, "REFUSE: a citation points at the deleted line itself\n");
        exit(1);
    }
    if ($b !== null && $a <= $deletedAt && $deletedAt <= $b) {
        fwrite(STDERR, "REFUSE: range {$a}-{$b} spans the deleted line; arithmetic cannot decide it\n");
        exit(1);
    }
    $na = $a > $deletedAt ? $a - 1 : $a;
    $nb = $b === null ? null : ($b > $deletedAt ? $b - 1 : $b);
    $from = 'AssessmentController.php:'.$a.($b === null ? '' : '-'.$b);
    $to = 'AssessmentController.php:'.$na.($nb === null ? '' : '-'.$nb);
    if ($from !== $to) {
        $tokens[$from] = $to;
    }
}

if ($tokens === []) {
    fwrite(STDERR, "REFUSE: nothing to shift — the cites were already below the deleted line?\n");
    exit(1);
}

$newXml = $xml;
foreach ($tokens as $from => $to) {
    $newXml = str_replace($from, $to, $newXml);
}
// The guarantee, stated as counts: every occurrence that existed before must exist at the new
// number, and no occurrence of the old number may survive. Without this, a token consumed as the
// tail of another replacement would silently leave a stale citation behind and the dry run would
// still look tidy.
foreach ($tokens as $from => $to) {
    $was = substr_count($xml, $from);
    $now = substr_count($newXml, $to);
    if ($now !== $was || substr_count($newXml, $from) !== 0) {
        fwrite(STDERR, "REFUSE: '{$from}' had {$was} occurrence(s), '{$to}' now has {$now}, "
            .substr_count($newXml, $from)." stale\n");
        exit(1);
    }
}
// Longest-first would matter if one token were a prefix of another; check that none is.
foreach ($tokens as $a => $ta) {
    foreach ($tokens as $b => $tb) {
        if ($a !== $b && str_contains($b, $a)) {
            fwrite(STDERR, "REFUSE: '{$a}' is a prefix of '{$b}' — one replace would corrupt the other\n");
            exit(1);
        }
    }
}
if (simplexml_load_string($newXml) === false) {
    fwrite(STDERR, "REFUSE: document.xml no longer parses\n");
    exit(1);
}
if (substr_count($newXml, '<w:t') !== substr_count($xml, '<w:t')) {
    fwrite(STDERR, "REFUSE: element count moved — this pass must be number-only\n");
    exit(1);
}

printf("shifting %d distinct citations:\n", count($tokens));
foreach ($tokens as $from => $to) {
    printf("  %-34s -> %s  (%d occurrence(s))\n", $from, $to, substr_count($xml, $from));
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-10-pass73.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();
$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();
if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: disk does not hold the edited document.xml\n");
    exit(1);
}
echo "Wrote {$src}\n";
