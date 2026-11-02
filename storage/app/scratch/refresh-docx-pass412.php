<?php

/**
 * Pass 412 — the §1.1 row for the ninth dashboard card.
 *
 * G-46's last exception closed: `DeskQueues::blocking()` is now the base `BlockingController::index()` builds its
 * rows from, so the card names the same rows as the page beneath it. This pass writes only the revision row; the
 * register sentence it closes was already re-taken by pass 411, and no cell needs rebuilding here.
 *
 * The template is the row pass 409 wrote, which is the newest in §1.1. A clone anchors on that row's own opening
 * words, never on a predecessor's — pass 398 met the cost of the other choice.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass412.php [--apply]
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
$opening = 'The ninth dashboard card now counts the rows its own page lists.';
$clauses = [
    $opening,
    'Sheet item 9 was the last school question inside §28.3 G-46: the Blocking badge counted enrollments whose first open box is that office while `BlockingController::index()` lists timetable blocks, and choosing one silently would have been a decision nobody was entitled to make. The owner ruled on 2026-11-02 that the card counts what its own page lists, so `App\\Support\\DeskQueues::blocking()` is now the base that action builds its rows from, the exception in that class\'s docblock is gone, and seven desk lists rather than six take their population from the same call their card counts. The enrollments waiting at the box were not deleted — that figure is what the workflow bell addresses — and the falsifier grew the first two legs in this family that plant a different SUBJECT rather than a different filter, which is the shape no equality-over-filters case can see.',
    'Two method names in `DashboardQueueCountMatchesDeskTest` lost their numerals the day the counts moved, because a number in a test name is a membership claim of exactly the kind §28 records elsewhere and it rots silently: the harness that filters by those names would otherwise report `No tests found` as a failed control over a green tree. §28.3\'s closing sentence, the matrix\'s §8 row, its G-46 row and its section heading were re-taken in the same batch, since one ruling must reach every tally that states it rather than only the row it decides.',
    'The citation work was the usual half-finish: `lane76-shift-cites.php` moved every aged token in the three files this batch touched, deferring two bare numbers whose nearest named file belongs to another document — the tail-of-a-longer-pointer hazard, correctly refused rather than guessed. The tool prints its own per-run totals, so this row states none. On the markdown side the byte resolver answered `12 HITS` for one endpoint because that line is a bare brace, so the pair came instead from the window the docx tool proved byte-identical across three copies, which is the only claim a re-point is allowed to make.',
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

$rows = $rowsForAnchor($xml, $date.' The two numbers the ADS guide asks the school for');
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

$backup = $root.'/storage/app/scratch/pass412-backup-'.date('Ymd-His').'.docx';
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
    'the predecessor row still exists' => count($rowsForAnchor($read, $date.' The two numbers the ADS guide asks the school for')) === 1,
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

printf("written; row present, exact, last in the table, predecessor intact; backup %s\n", basename($backup));
echo "Now re-dump and run sweep-docx-citations, lane37, lane61, verify-docx and docx-ooxml-check.\n";
