<?php

/**
 * Pass 140 — §23.6.1's complete list of enforced uniqueness gains the key it was missing.
 *
 * The section says "Every unique index in the EMS domain tables" and counts eight keys in seven tables. Asking
 * the server gives nine across seven: `uq_documentprintlog_issue (enrollmentId, documentType, documentNumber)`
 * was created by 2026_09_30_1000000003_unify_document_number_and_make_it_unique.php and the list was never
 * told. A reader treats §23.6.1 as the boundary of what MySQL will defend, so an omitted key is a rule the
 * document implies the database does not enforce — storage/app/scratch/lane44-unique-keys.php now checks the
 * whole set rather than the arithmetic.
 *
 * The row is CLONED from the first key row so it inherits widths, borders and run properties, and inserted
 * before it: the list reads alphabetically by key name, and uq_documentprintlog_issue sorts first.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$values = [
    'uq_documentprintlog_issue',
    'enrollmentId, documentType, documentNumber',
    'One issuance number per enrollment per document type, so the print log can be counted: a number that '
    .'appears twice on one enrollment is a duplicate or a forgery, not a copy re-issued under the same number.',
    'It bounds nothing about how many copies exist, only that two rows cannot carry one number. The numbers '
    .'themselves were re-cut in insertion order by the migration that added this key, and a print with no '
    .'enrollment attached yet groups under the sentinel 0 — see §23.6.1’s source migration.',
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$at = strpos($xml, 'uq_enrollment_subject_attempt');
$guard($at !== false, 'the first mention of the anchor key is found');

$rowStart = $at === false ? false : strrpos(substr($xml, 0, (int) $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', (int) $at) + 7;
$row = $rowStart === false ? '' : substr($xml, (int) $rowStart, (int) $rowEnd - (int) $rowStart);

// The name occurs three times in the document; identity is proven by the row's own cells, not by the offset.
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 4, 'the template row has exactly four cells');
preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cm[0][0], $idm);
$guard(str_contains((string) ($idm[1] ?? ''), 'uq_enrollment_subject_attempt'),
    'the row addressed IS the enrollment-subject key row, not another mention of that name');

$guard(substr($xml, (int) $rowStart - 7, 7) === '</w:tr>', 'the row is preceded by a sibling row’s close');
$guard(substr($xml, (int) $rowStart, 6) === '<w:tr>', 'and the row itself opens where we cut');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    $start = $m[1][1];

    return substr($cell, 0, $start).$escaped.substr($cell, $start + strlen($m[1][0]));
};

$clone = $row;
foreach ($cm[0] as $i => $cell) {
    $guard(substr_count($cell, '</w:t>') === 1, "cell {$i} holds exactly one text run");
    $clone = str_replace($cell, $setText($cell, $values[$i]), $clone);
}
$guard($clone !== $row, 'the clone carries new text');
$guard(str_contains($clone, $values[0]), 'the clone carries its own key name');
$guard(! str_contains($clone, 'uq_enrollment_subject_attempt'), 'the clone no longer carries the template’s key name');
$guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the clone is one row and no more');

$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$expect = [];
foreach ($needle as $k => $n) {
    $expect[$k] = substr_count($clone, $n);
    $guard($expect[$k] > 0, "the clone contributes at least one {$k}");
}
$before = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
}

$new = substr_replace($xml, $clone, (int) $rowStart, 0);
foreach ($needle as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowStart) + 7;
preg_match_all('/<w:tr.*?<\/w:tr>/s', substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart), $check);
$guard(count($check[0]) === 10, 'the table now holds 1 header + 9 key rows, recounted from the bytes');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses');
libxml_clear_errors();

printf("\nnew row: %d bytes\n", strlen($clone));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass140.docx');

$z0 = new ZipArchive();
$z0->open($src);
$partsBefore = count($z0);
$z0->close();

$zip = new ZipArchive();
if ($zip->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $new);
$zip->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();
if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");
    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
exit(0);
