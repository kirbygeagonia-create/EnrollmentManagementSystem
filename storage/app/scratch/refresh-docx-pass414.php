<?php

/**
 * Pass 414 — the §1.1 row for the nineteenth sortable table.
 *
 * The workflow monitor's list now sorts through the shared mechanism. The register's own counts were moved by pass
 * 413; this row records the batch, and states no per-class case or assertion figure, because those are properties of
 * whichever run printed them and the delivered reading re-takes them beside the class name.
 *
 * The template is the row pass 412 wrote, which is the newest in §1.1; a clone anchors on that row's own opening
 * words, never on a predecessor's.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass414.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};
$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};
$rowsForAnchor = static function (string $xml, string $anchor) use ($rowText): array {
    $hits = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        if (strncmp($rowText(substr($xml, $start, $end - $start)), $anchor, strlen($anchor)) === 0) {
            $hits[] = [$start, $end];
        }
        $at = $end;
    }

    return $hits;
};

$date = '2026-11-02';
$opening = 'The nineteenth list table in the shared control sorts, and the instrument that owns the count wrote the to-do list.';
$clauses = [
    $opening,
    'The institution-wide workflow monitor had one order since it was written — newest record first — so an overseer looking for a student or a program could only page through the term. `App\\Support\\ListSort` now answers its order, with four columns carrying a header and four deliberately not: the surname and the program reach their tables through a hop, the type and the record status sit on the row the page already reads, and `progress`, the next desk, the last signed date and the return category are built per row from the workflow form itself, so no column the query can name orders them and a header for one of those would be the defect §28.3 owns for filters.',
    'Both hops were chosen by asking the schema and the rows rather than by copying the last screen — one earlier table needed a LEFT hop because its key is null on almost every row, another an INNER one because its column is `NOT NULL` — and this page\'s two foreign keys are `NOT NULL` with no null row on the live database, so an order by a name cannot delete the record it orders. The order is applied only after every summary tile has been cloned from the builder, and the harness plants the retired hard-coded order back to prove the structural case sees it although every number stays equal.',
    '`storage/app/scratch/lane140-measure-wired-screens.php` is what listed the phrases this batch had to re-take: it read the wired and owed counts out of both registers by shape, named each stale one with the sentence it sits in, and refused until each equalled the tree — three claims in §28.3 and four in the matrix. The dated §1.1 rows it reported as records were left as written, because re-typing a figure inside a dated row states a number that was not true on that day. `mutate-g40-workflow-monitor-tests.php` carries the falsifier; the withheld leg and its reason are in that file.',
];
$body = implode(' ', $clauses);

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

if (substr_count($xml, $escape($body)) !== 0) {
    $fail('this pass has already run: its own row text is in the document.');
}

$rows = $rowsForAnchor($xml, $date.' The ninth dashboard card now counts the rows its own page lists.');
if (count($rows) !== 1) {
    $fail('the newest revision row matched '.count($rows).' row(s), expected exactly 1 — re-read §1.1 before cloning.');
}
[$start, $end] = $rows[0];
$template = substr($xml, $start, $end - $start);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
if (count($cm[0]) !== 2) {
    $fail('the revision row this pass clones holds '.count($cm[0]).' cells, expected 2.');
}
foreach ($cm[0] as $cell) {
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            $fail('a cell of the revision row holds a SECOND paragraph with text — read it before cloning.');
        }
    }
}

$values = [$date, $body];
$clone = $template;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm2, PREG_OFFSET_CAPTURE);
$pairs = [];
foreach ($cm2[0] as $j => $x) {
    $pairs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
}
usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
foreach ($pairs as [$j, $at, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
    foreach (array_slice($pm[0], 1) as $extra) {
        $cell = str_replace($extra, '', $cell);
    }
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $cellXml = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $cellXml);
    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        $fail("the replacement for cell {$j} holds more than one text run.");
    }
    $clone = substr_replace($clone, $new, $at, $len);
}
if (substr_count($clone, '<w:tc>') !== 2) {
    $fail('the clone lost or gained a cell.');
}
$xml2 = substr_replace($xml, $clone, $end, 0);

printf("stage 1 built: one §1.1 row (%d bytes). %s\n", strlen($body), $apply ? 'applying' : 'DRY RUN');

if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass414-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}
$zip = new ZipArchive;
if ($zip->open($docx, ZipArchive::CREATE) !== true) {
    $fail('the docx did not reopen for writing the row.');
}
$zip->addFromString('word/document.xml', $xml2);
$zip->close();

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$read = (string) $again->getFromName('word/document.xml');
$again->close();

$newRows = $rowsForAnchor($read, $date.' '.$opening);
$checks = [
    'the row is present and exact' => count($newRows) === 1
        && $rowText(substr($read, $newRows[0][0], $newRows[0][1] - $newRows[0][0])) === $date.' '.$body,
    'both predecessor rows still exist' => count($rowsForAnchor($read, $date.' The ninth dashboard card now counts the rows its own page lists.')) === 1
        && count($rowsForAnchor($read, $date.' The two numbers the ADS guide asks the school for')) === 1,
    'this row is the last in its table' => (function (string $read, array $newRows): bool {
        $tblEnd = strpos($read, '</w:tbl>', $newRows[0][1]);
        $nextTr = strpos($read, '<w:tr', $newRows[0][1]);

        return $nextTr === false || $tblEnd === false || $nextTr > $tblEnd;
    })($read, $newRows),
];
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($backup, $docx);
    $fail('read-back refused ['.implode('; ', $failed).'] — restored from '.$backup);
}

$prev = libxml_use_internal_errors(true);
$doc = new DOMDocument;
$parsed = $doc->loadXML($read);
libxml_use_internal_errors($prev);
if (! $parsed) {
    copy($backup, $docx);
    $fail('document.xml no longer parses — restored from '.$backup);
}

printf("written; row present, exact, last in the table, both predecessors intact; backup %s\n", basename($backup));
echo "Now re-dump and run sweep-docx-citations, lane37, verify-docx, docx-ooxml-check and the Word open test.\n";
